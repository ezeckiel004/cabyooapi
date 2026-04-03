<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Setting;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    public function __construct()
    {
        // Initialiser Stripe avec la clé secrète
        $stripeKey = config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY');
        
        if (empty($stripeKey)) {
            Log::error('❌ STRIPE_SECRET_KEY not found in config or env');
        } else {
            Log::info('✅ Stripe initialized with key');
        }
        
        Stripe::setApiKey($stripeKey);
    }

    /**
     * Créer un Payment Intent
     */
    public function createPaymentIntent(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|integer|min:100', // Minimum 1€ (100 centimes)
                'currency' => 'required|string|size:3',
                'description' => 'nullable|string|max:500',
            ]);

            Log::info('💳 Creating Payment Intent:', [
                'amount_cents' => $validated['amount'],
                'amount_euros' => $validated['amount'] / 100,
                'currency' => $validated['currency'],
            ]);

            // Créer le Payment Intent
            // 🔐 SÉCURITÉ CLIENTS FRANÇAIS: Limiter UNIQUEMENT aux cartes bancaires
            // Les clients français sont méfiants - éliminer Amazon Pay, Link, Apple Pay, EPS, Bank Redirect, etc.
            $paymentIntent = PaymentIntent::create([
                'amount' => $validated['amount'], // Montant en centimes
                'currency' => $validated['currency'],
                'description' => $validated['description'] ?? 'Cabyoo VTC Ride',
                // ✅ STRICT: UNIQUEMENT cartes bancaires (CB, Visa, Mastercard, Amex)
                // Cela élimine: Amazon Pay, Link, EPS, Apple Pay, Google Pay, Bank Transfers, etc.
                'payment_method_types' => ['card'],
                'metadata' => [
                    'client_id' => auth()->id(),
                    'app' => 'cabyoo-mobile',
                ],
            ]);

            Log::info('✅ Payment Intent créé (CARTES UNIQUEMENT):', [
                'id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'payment_method_types' => $paymentIntent->payment_method_types,
            ]);

            return response()->json([
                'message' => 'Payment Intent créé',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $paymentIntent->amount,
                    'currency' => $paymentIntent->currency,
                    'status' => $paymentIntent->status,
                    // ✅ Confirmer que UNIQUEMENT les cartes sont acceptées
                    'payment_method_types' => $paymentIntent->payment_method_types,
                ],
            ], 201);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('❌ Erreur Stripe:', [
                'message' => $e->getMessage(),
                'error' => $e->getStripeCode(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de la création du paiement',
                'error' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('❌ Erreur serveur:', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Erreur serveur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirmer le paiement (webhook)
     */
    public function confirmPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'client_secret' => 'required|string',
            ]);

            // Récupérer le Payment Intent depuis Stripe
            $paymentIntents = PaymentIntent::all([
                'limit' => 1,
            ]);

            $paymentIntent = null;
            foreach ($paymentIntents->data as $intent) {
                if ($intent->client_secret === $validated['client_secret']) {
                    $paymentIntent = $intent;
                    break;
                }
            }

            if (!$paymentIntent) {
                return response()->json([
                    'message' => 'Payment Intent non trouvé',
                ], 404);
            }

            // Vérifier le statut du paiement
            $isPaid = $paymentIntent->status === 'succeeded';

            Log::info('🔍 Paiement vérifié:', [
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount,
                'is_paid' => $isPaid,
            ]);

            return response()->json([
                'message' => 'Paiement confirmé',
                'data' => [
                    'status' => $paymentIntent->status,
                    'amount' => $paymentIntent->amount,
                    'currency' => $paymentIntent->currency,
                    'is_paid' => $isPaid,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur confirmation paiement:', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Erreur lors de la confirmation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook Stripe (pour les paiements asynchrones)
     */
    public function handleWebhook(Request $request)
    {
        $endpointSecret = env('STRIPE_WEBHOOK_SECRET');
        $payload = $request->getContent();
        $sigHeader = $request->header('stripe-signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );

            Log::info('📨 Webhook Stripe reçu:', ['type' => $event['type']]);

            // Traiter les différents événements
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event['data']['object'];
                    Log::info('✅ Webhook: Paiement réussi', ['id' => $paymentIntent['id']]);
                    
                    // 🔴 SÉCURITÉ: Mettre à jour la course en base!
                    $ride = Ride::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
                    if ($ride) {
                        $updateData = [
                            'deposit_status' => 'paid',
                            'deposit_paid_at' => now(),
                            'status' => 'pending', // Rendre visible aux chauffeurs
                        ];
                        
                        // ✅ PAYMENT_STATUS only to 'paid' if it's NOT a deposit payment
                        // Pour les acomptes: payment_status reste 'pending' jusqu'au paiement du solde complet
                        if (isset($paymentIntent['metadata']['type']) && $paymentIntent['metadata']['type'] === 'ride_deposit') {
                            // C'est un acompte - payment_status reste pending
                            Log::info('💳 Webhook: Acompte payé, payment_status reste pending', [
                                'ride_id' => $ride->id,
                            ]);
                        } else {
                            // C'est le paiement complet - payment_status passe à paid
                            $updateData['payment_status'] = 'paid';
                            Log::info('✅ Webhook: Paiement complet traité, payment_status = paid', [
                                'ride_id' => $ride->id,
                            ]);
                        }
                        
                        $ride->update($updateData);
                        
                        Log::info('✅ Course mise à jour après webhook succès', [
                            'ride_id' => $ride->id,
                            'stripe_intent_id' => $paymentIntent['id'],
                        ]);
                    } else {
                        Log::warning('⚠️ Webhook succès mais course non trouvée', [
                            'stripe_intent_id' => $paymentIntent['id'],
                        ]);
                    }
                    break;

                case 'payment_intent.payment_failed':
                    $paymentIntent = $event['data']['object'];
                    Log::warning('❌ Webhook: Paiement échoué', ['id' => $paymentIntent['id']]);
                    
                    // 🔴 SÉCURITÉ: Mettre à jour le statut en cas d'échec
                    $ride = Ride::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
                    if ($ride) {
                        $ride->update([
                            'deposit_status' => 'failed',
                            'status' => 'cancelled',
                            'payment_error' => isset($paymentIntent['last_payment_error']) ? 
                                json_encode($paymentIntent['last_payment_error']) : 'Paiement échoué',
                        ]);
                        
                        Log::warning('Course marquée comme échouée', [
                            'ride_id' => $ride->id,
                            'stripe_intent_id' => $paymentIntent['id'],
                        ]);
                    }
                    break;

                case 'charge.refunded':
                    Log::warning('💸 Webhook: Remboursement traité');
                    // Mettre à jour les courses remboursées si nécessaire
                    break;

                default:
                    Log::info('📝 Webhook événement non traité: ' . $event['type']);
            }

            return response()->json(['success' => true]);

        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('❌ Signature webhook invalide', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Signature invalide'], 403);

        } catch (\Exception $e) {
            Log::error('❌ Erreur webhook:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Erreur webhook'], 500);
        }
    }
}
