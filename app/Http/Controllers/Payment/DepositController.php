<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Setting;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DepositController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
    }

    /**
     * Initier le paiement d'acompte pour une course
     * POST /api/deposit/initiate
     */
    public function initiateDeposit(Request $request)
    {
        try {
            $validated = $request->validate([
                'ride_id' => 'required|exists:rides,id',
                'currency' => 'required|string|size:3',
            ]);

            $ride = Ride::findOrFail($validated['ride_id']);
            $user = auth()->user();

            // Vérifier que la course appartient au client
            if ($ride->client_id !== $user->id) {
                return response()->json([
                    'message' => 'Accès non autorisé à cette course',
                ], 403);
            }

            // Vérifier que l'acompte n'a pas déjà été payé
            if ($ride->deposit_status === 'paid') {
                return response()->json([
                    'message' => 'L\'acompte de cette course a déjà été payé',
                ], 400);
            }

            // 🌍 TOUT EN EUROS - Simplification complète
            $minPrice = (float) Setting::getValue('min_price', 2.0); // 2€ minimum
            $estimatedPrice = (float) $ride->estimated_price; // Toujours en euros du frontend
            $estimatedPrice = max($estimatedPrice, $minPrice); // Respecter le minimum
            $depositAmount = round($estimatedPrice * 0.10, 2); // 10% en euros

            // 🪙 Conversion UNIQUEMENT pour Stripe (euros → centimes)
            $depositAmountCents = (int) round($depositAmount * 100);

            Log::info('💳 [DEPOSIT] Initiating payment:', [
                'ride_id' => $ride->id,
                'estimated_price_eur' => $estimatedPrice . '€',
                'deposit_amount_eur' => $depositAmount . '€',
                'deposit_amount_cents' => $depositAmountCents . ' ¢',
                'currency' => $validated['currency'],
            ]);

            // ✅ Créer Payment Intent Stripe (montant en centimes)
            $paymentIntent = PaymentIntent::create([
                'amount' => $depositAmountCents, // UNIQUEMENT ici en centimes
                'currency' => strtolower($validated['currency']),
                'description' => 'Acompte (10%) - Course #' . $ride->ride_number,
                'payment_method_types' => ['card'], // 🔒 CARTE SEULE
                'metadata' => [
                    'ride_id' => $ride->id,
                    'client_id' => $user->id,
                    'type' => 'ride_deposit',
                    'app' => 'cabyoo-mobile',
                ],
            ]);

            // Mettre à jour la course avec les infos du paiement
            $ride->update([
                'deposit_amount' => $depositAmount,
                'deposit_status' => 'pending',
                'stripe_payment_intent_id' => $paymentIntent->id,
            ]);

            Log::info('✅ Deposit initiated:', [
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $depositAmount,
            ]);

            return response()->json([
                'message' => 'Paiement d\'acompte initié',
                'data' => [
                    'ride_id' => $ride->id,
                    'ride_number' => $ride->ride_number,
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $depositAmount,
                    'amount_cents' => $depositAmountCents,
                    'currency' => $validated['currency'],
                    'status' => $paymentIntent->status,
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
     * Confirmer le paiement d'acompte
     * POST /api/deposit/confirm
     */
    public function confirmDeposit(Request $request)
    {
        try {
            $validated = $request->validate([
                'ride_id' => 'required|exists:rides,id',
                'payment_intent_id' => 'required|string',
            ]);

            $ride = Ride::findOrFail($validated['ride_id']);
            $user = auth()->user();

            // Vérifier que la course appartient au client
            if ($ride->client_id !== $user->id) {
                return response()->json([
                    'message' => 'Accès non autorisé à cette course',
                ], 403);
            }

            // Récupérer le Payment Intent depuis Stripe
            $paymentIntent = PaymentIntent::retrieve($validated['payment_intent_id']);

            Log::info('🔍 Checking payment intent status:', [
                'intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
            ]);

            if ($paymentIntent->status === 'succeeded') {
                // Le paiement a réussi
                $ride->update([
                    'deposit_status' => 'paid',
                    'deposit_paid_at' => now(),
                    'stripe_payment_method_id' => $paymentIntent->payment_method,
                    'status' => 'pending', // La course est maintenant en attente de driver
                ]);

                Log::info('✅ Deposit payment succeeded:', [
                    'ride_id' => $ride->id,
                    'amount' => $ride->deposit_amount,
                ]);

                return response()->json([
                    'message' => 'Acompte payé avec succès',
                    'data' => [
                        'ride_id' => $ride->id,
                        'ride_number' => $ride->ride_number,
                        'deposit_status' => 'paid',
                        'deposit_amount' => $ride->deposit_amount,
                        'ride_status' => $ride->status,
                    ],
                ], 200);

            } elseif ($paymentIntent->status === 'processing') {
                return response()->json([
                    'message' => 'Le paiement est en cours de traitement',
                    'data' => [
                        'ride_id' => $ride->id,
                        'status' => 'processing',
                    ],
                ], 202);

            } elseif ($paymentIntent->status === 'requires_payment_method') {
                // Le client doit fournir une méthode de paiement
                $ride->update([
                    'deposit_status' => 'failed',
                    'payment_error' => 'Méthode de paiement requise',
                ]);

                return response()->json([
                    'message' => 'Une méthode de paiement est requise',
                    'data' => [
                        'ride_id' => $ride->id,
                        'status' => 'requires_payment_method',
                    ],
                ], 400);

            } else {
                // Paiement échoué
                $errorMessage = $paymentIntent->last_payment_error?->message ?? 'Paiement échoué';
                $ride->update([
                    'deposit_status' => 'failed',
                    'payment_error' => $errorMessage,
                ]);

                Log::error('❌ Deposit payment failed:', [
                    'ride_id' => $ride->id,
                    'error' => $errorMessage,
                ]);

                return response()->json([
                    'message' => 'Le paiement a échoué',
                    'data' => [
                        'ride_id' => $ride->id,
                        'status' => 'failed',
                        'error' => $errorMessage,
                    ],
                ], 400);
            }

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('❌ Erreur Stripe:', [
                'message' => $e->getMessage(),
                'error' => $e->getStripeCode(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de la vérification du paiement',
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
     * Rembourser l'acompte en cas d'annulation
     * POST /api/deposit/refund
     */
    public function refundDeposit(Request $request)
    {
        try {
            $validated = $request->validate([
                'ride_id' => 'required|exists:rides,id',
            ]);

            $ride = Ride::findOrFail($validated['ride_id']);
            $user = auth()->user();

            // Vérifier que la course appartient au client
            if ($ride->client_id !== $user->id) {
                return response()->json([
                    'message' => 'Accès non autorisé à cette course',
                ], 403);
            }

            // Vérifier que l'acompte a été payé
            if ($ride->deposit_status !== 'paid') {
                return response()->json([
                    'message' => 'Aucun acompte à rembourser pour cette course',
                ], 400);
            }

            if (!$ride->stripe_payment_intent_id) {
                return response()->json([
                    'message' => 'ID de paiement non trouvé',
                ], 400);
            }

            // Créer un remboursement
            $paymentIntent = PaymentIntent::retrieve($ride->stripe_payment_intent_id);
            
            if ($paymentIntent->charges->data) {
                $charge = $paymentIntent->charges->data[0];
                
                // Créer le remboursement
                $refund = $charge->refund();

                $ride->update([
                    'deposit_status' => 'refunded',
                ]);

                Log::info('✅ Deposit refunded:', [
                    'ride_id' => $ride->id,
                    'refund_id' => $refund->id,
                    'amount' => $ride->deposit_amount,
                ]);

                return response()->json([
                    'message' => 'Acompte remboursé avec succès',
                    'data' => [
                        'ride_id' => $ride->id,
                        'refund_id' => $refund->id,
                        'amount' => $ride->deposit_amount,
                        'status' => 'refunded',
                    ],
                ], 200);
            }

            return response()->json([
                'message' => 'Impossible de rembourser le paiement',
            ], 400);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('❌ Erreur Stripe:', [
                'message' => $e->getMessage(),
                'error' => $e->getStripeCode(),
            ]);

            return response()->json([
                'message' => 'Erreur lors du remboursement',
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
}
