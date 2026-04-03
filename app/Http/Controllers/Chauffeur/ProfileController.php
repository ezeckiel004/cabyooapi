<?php

namespace App\Http\Controllers\Chauffeur;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // Voir le profil
    public function show()
    {
        $user = Auth::user();
        $user->load('driverDetail');

        // Statistiques du jour
        $todayStats = $user->driverRides()
            ->whereDate('created_at', today())
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as count, SUM(driver_earnings) as earnings')
            ->first();

        // Statistiques de la semaine
        $weekStats = $user->driverRides()
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as count, SUM(driver_earnings) as earnings')
            ->first();

        // Dernières courses
        $recentRides = $user->driverRides()
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Castifier les prix en float pour éviter les erreurs de sérialisation JSON
        if ($user->driverDetail) {
            $user->driverDetail->base_price = (float) ($user->driverDetail->base_price ?? 0);
            $user->driverDetail->price_per_km = (float) ($user->driverDetail->price_per_km ?? 0);
        }

        return response()->json([
            'user' => $user,
            'stats' => [
                'today' => [
                    'rides' => $todayStats->count ?? 0,
                    'earnings' => (float) ($todayStats->earnings ?? 0),
                ],
                'week' => [
                    'rides' => $weekStats->count ?? 0,
                    'earnings' => (float) ($weekStats->earnings ?? 0),
                ],
                'all_time' => [
                    'rides' => $user->driverDetail->completed_rides ?? 0,
                    'earnings' => (float) ($user->driverDetail->total_earnings ?? 0),
                    'rating' => (float) ($user->driverDetail->rating ?? 0),
                ],
            ],
            'recent_rides' => $recentRides,
        ]);
    }

    // Mettre à jour le profil
    public function update(Request $request)
    {
        $user = Auth::user();

        // Validation de base
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['sometimes', 'string', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'sometimes|required_with:password',
            'password' => 'sometimes|min:8|confirmed',
            // Champs driver_details
            'driver_license' => 'sometimes|string|max:255',
            'driver_license_expiry' => 'sometimes|string|nullable',
            'car_model' => 'sometimes|string|max:255',
            'car_plate' => 'sometimes|string|max:255',
            'car_color' => 'sometimes|string|max:255',
            'car_year' => 'sometimes|integer|min:1990|max:' . (date('Y') + 1),
            'car_registration' => 'sometimes|string|max:255',
            'car_seats' => 'sometimes|integer|min:1|max:10',
            'car_type' => 'sometimes|string|in:standard,berline,suv,monospace,coupé,cabriolet,break',
            'car_insurance' => 'sometimes|string|max:255',
            'car_insurance_expiry' => 'sometimes|string|nullable',
            'car_inspection_expiry' => 'sometimes|string|nullable',
            'year_of_experience' => 'sometimes|integer|min:0',
            'base_price' => 'sometimes|numeric|min:0',
            'price_per_km' => 'sometimes|numeric|min:0',
            // 💳 NOUVEAU: Préférences de paiement
            'payment_method' => 'sometimes|string|in:bank_transfer,paypal,stripe',
            'bank_account_holder' => 'sometimes|string|max:255',
            'bank_iban' => 'sometimes|string|max:34',
            'bank_bic' => 'sometimes|string|max:11',
            'paypal_email' => 'sometimes|email|max:255',
            'stripe_id' => 'sometimes|string|max:255',
        ]);

        // 💳 Validation conditionnelle pour les moyens de paiement
        if ($request->has('payment_method')) {
            $paymentMethod = $request->input('payment_method');
            
            if ($paymentMethod === 'bank_transfer') {
                // 🏦 Virement bancaire: tous les champs requis
                $this->validate($request, [
                    'bank_account_holder' => 'required|string|max:255',
                    'bank_iban' => 'required|string|max:34',
                    'bank_bic' => 'required|string|max:11',
                ], [
                    'bank_account_holder.required' => 'Le titulaire du compte est requis.',
                    'bank_iban.required' => 'L\'IBAN est requis.',
                    'bank_bic.required' => 'Le code BIC/SWIFT est requis.',
                ]);
                
                $validated['bank_account_holder'] = $request->input('bank_account_holder');
                $validated['bank_iban'] = $request->input('bank_iban');
                $validated['bank_bic'] = $request->input('bank_bic');
                $validated['paypal_email'] = null;
                $validated['stripe_id'] = null;
                
            } elseif ($paymentMethod === 'paypal') {
                // 💰 PayPal: email requis
                $this->validate($request, [
                    'paypal_email' => 'required|email|max:255',
                ], [
                    'paypal_email.required' => 'L\'email PayPal est requis.',
                    'paypal_email.email' => 'L\'email PayPal doit être valide.',
                ]);
                
                $validated['paypal_email'] = $request->input('paypal_email');
                $validated['bank_account_holder'] = null;
                $validated['bank_iban'] = null;
                $validated['bank_bic'] = null;
                $validated['stripe_id'] = null;
                
            } elseif ($paymentMethod === 'stripe') {
                // 🔐 Stripe: ID/email requis
                $this->validate($request, [
                    'stripe_id' => 'required|string|max:255',
                ], [
                    'stripe_id.required' => 'L\'ID Stripe est requis.',
                ]);
                
                $validated['stripe_id'] = $request->input('stripe_id');
                $validated['bank_account_holder'] = null;
                $validated['bank_iban'] = null;
                $validated['bank_bic'] = null;
                $validated['paypal_email'] = null;
            }
        }

        // Convertir les dates du format JJ/MM/AAAA en Y-M-D
        $dateFields = ['driver_license_expiry', 'car_insurance_expiry', 'car_inspection_expiry'];
        foreach ($dateFields as $field) {
            if (!empty($validated[$field])) {
                try {
                    $date = \DateTime::createFromFormat('d/m/Y', $validated[$field]);
                    if ($date === false) {
                        // Essayer le format standard Y-M-D aussi
                        $date = new \DateTime($validated[$field]);
                    }
                    $validated[$field] = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // Si la conversion échoue, garder la valeur originale
                    // Laravel essayera de la sauvegarder comme texte
                }
            }
        }

        // Vérifier le mot de passe actuel si changement de mot de passe
        if ($request->has('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'Le mot de passe actuel est incorrect'
                ], 422);
            }

            $validated['password'] = Hash::make($request->password);
        }

        // Séparer les données utilisateur et driver_details
        $userFields = ['name', 'email', 'phone', 'password'];
        $driverFields = [
            'driver_license',
            'driver_license_expiry',
            'car_model',
            'car_plate',
            'car_color',
            'car_year',
            'car_registration',
            'car_seats',
            'car_type',
            'car_insurance',
            'car_insurance_expiry',
            'car_inspection_expiry',
            'year_of_experience',
            'base_price',
            'price_per_km',
            // 💳 NOUVEAU: Préférences de paiement (TOUS les champs!)
            'payment_method',
            'bank_account_holder',
            'bank_iban',
            'bank_bic',
            'paypal_email',      // ✅ AJOUTÉ
            'stripe_id',         // ✅ AJOUTÉ
        ];

        // Mettre à jour les données utilisateur
        $userData = array_filter($validated, fn($key) => in_array($key, $userFields), ARRAY_FILTER_USE_KEY);
        if (!empty($userData)) {
            $user->update($userData);
        }

        // Mettre à jour les données driver_details
        $driverData = array_filter($validated, fn($key) => in_array($key, $driverFields), ARRAY_FILTER_USE_KEY);
        
        \Log::info('DRIVER_DATA_UPDATE', [
            'user_id' => $user->id,
            'driverData' => $driverData,
            'hasDriver' => $user->isDriver(),
        ]);
        
        if (!empty($driverData) && $user->isDriver()) {
            \Log::info('UPDATING_DRIVER_DETAIL', $driverData);
            $user->driverDetail()->updateOrCreate(
                ['user_id' => $user->id],
                $driverData
            );
        }

        // Load driver details
        if ($user->isDriver()) {
            $user->load('driverDetail');
            
            // Castifier les prix en float pour éviter les erreurs de sérialisation JSON
            if ($user->driverDetail) {
                $user->driverDetail->base_price = (float) ($user->driverDetail->base_price ?? 0);
                $user->driverDetail->price_per_km = (float) ($user->driverDetail->price_per_km ?? 0);
            }
        }

        // Récupérer le token actuel depuis la requête
        $token = $request->bearerToken();

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => $user,
            'token' => $token  // Retourner le token pour que le client le conserve
        ]);
    }

    // Mettre à jour la disponibilité
    public function updateAvailability(Request $request)
    {
        $user = Auth::user();

        if (!$user->driverDetail) {
            return response()->json([
                'message' => 'Vous n\'êtes pas un chauffeur'
            ], 400);
        }

        $request->validate([
            'is_available' => 'required|boolean',
        ]);

        $user->driverDetail->update([
            'is_available' => $request->is_available,
        ]);

        // Reload user with driver details
        $user->load('driverDetail');

        return response()->json([
            'message' => $request->is_available
                ? 'Vous êtes maintenant disponible pour les courses'
                : 'Vous n\'êtes plus disponible pour les courses',
            'user' => $user,
            'is_available' => $request->is_available,
        ]);
    }

    // Statistiques détaillées
    public function stats(Request $request)
    {
        $user = Auth::user();

        $period = $request->get('period', 'month'); // week, month, year, all_time

        // 🔴 IMPORTANT: Calculer les earnings à partir des paiements réels, pas de driver_earnings
        // Earnings = SUM(payments) × 0.80 (20% commission plateforme)
        $query = $user->driverRides()
            ->where('rides.status', 'completed')
            ->join('payments', 'rides.id', '=', 'payments.ride_id')
            ->where('payments.status', 'completed'); // Seulement les paiements complétés

        switch ($period) {
            case 'week':
                $query->whereBetween('rides.created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('rides.created_at', now()->month);
                break;
            case 'year':
                $query->whereYear('rides.created_at', now()->year);
                break;
            case 'all_time':
                // No date filter - get all rides
                break;
        }

        // Statistiques de base - CALCULÉ À PARTIR DES PAIEMENTS
        // Récupérer la commission plateforme depuis les settings
        $platformCommissionPercent = (float) Setting::getValue('platform_commission_percent', 20);
        $driverCommissionPercent = 100 - $platformCommissionPercent;
        $commissionMultiplier = $driverCommissionPercent / 100; // Par exemple 0.80 si commission = 20%
        
        $stats = $query->selectRaw("
            COUNT(DISTINCT rides.id) as total_rides,
            (SUM(payments.amount) * {$commissionMultiplier}) as total_earnings,
            (SUM(payments.amount) * {$commissionMultiplier} / COUNT(DISTINCT rides.id)) as avg_earnings_per_ride,
            AVG(rides.distance_km) as avg_distance,
            SUM(rides.distance_km) as total_distance
        ")->first();

        Log::info('💰 Stats calculateed from payments', [
            'period' => $period,
            'driver_id' => $user->id,
            'total_earnings' => $stats->total_earnings ?? 0,
            'total_rides' => $stats->total_rides ?? 0,
        ]);

        // Pour "all_time", on retourne aussi la note du profil chauffeur
        $avgRating = 0;
        if ($period === 'all_time') {
            // La note est stockée dans driver_detail, pas dans rides
            $avgRating = (float) ($user->driverDetail?->rating ?? 0);
        }

        // Répartition par jour de la semaine - AUSSI À PARTIR DES PAIEMENTS
        $dayStats = $query->clone()
            ->selectRaw("DAYNAME(rides.created_at) as day, COUNT(DISTINCT rides.id) as rides, (SUM(payments.amount) * {$commissionMultiplier}) as earnings")
            ->groupBy('day')
            ->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
            ->get();

        // Répartition par heure
        $hourStats = [];
        for ($i = 0; $i < 24; $i++) {
            $hourData = $query->clone()
                ->whereRaw('HOUR(rides.created_at) = ?', [$i])
                ->selectRaw("COUNT(DISTINCT rides.id) as rides, (SUM(payments.amount) * {$commissionMultiplier}) as earnings")
                ->first();

            $hourStats[] = [
                'hour' => $i,
                'label' => sprintf('%02d:00', $i),
                'rides' => $hourData->rides ?? 0,
                'earnings' => (float) ($hourData->earnings ?? 0),
            ];
        }

        // Évolution sur la période
        $timeline = [];
        if ($period === 'week') {
            for ($i = 0; $i < 7; $i++) {
                $date = now()->startOfWeek()->addDays($i);
                $dayData = $query->clone()
                    ->whereDate('rides.created_at', $date)
                    ->selectRaw("COUNT(DISTINCT rides.id) as rides, (SUM(payments.amount) * {$commissionMultiplier}) as earnings")
                    ->first();

                $timeline[] = [
                    'date' => $date->format('Y-m-d'),
                    'day' => $date->format('l'),
                    'rides' => $dayData->rides ?? 0,
                    'earnings' => (float) ($dayData->earnings ?? 0),
                ];
            }
        } elseif ($period === 'month') {
            $daysInMonth = now()->daysInMonth;
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $dayData = $query->clone()
                    ->whereDay('rides.created_at', $i)
                    ->selectRaw("COUNT(DISTINCT rides.id) as rides, (SUM(payments.amount) * {$commissionMultiplier}) as earnings")
                    ->first();

                $timeline[] = [
                    'day' => $i,
                    'rides' => $dayData->rides ?? 0,
                    'earnings' => (float) ($dayData->earnings ?? 0),
                ];
            }
        }

        return response()->json([
            'period' => $period,
            'stats' => [
                'total_rides' => (int) ($stats->total_rides ?? 0),
                'total_earnings' => (float) ($stats->total_earnings ?? 0),
                'avg_earnings_per_ride' => (float) ($stats->avg_earnings_per_ride ?? 0),
                'avg_distance' => (float) ($stats->avg_distance ?? 0),
                'total_distance' => (float) ($stats->total_distance ?? 0),
                'average_rating' => $avgRating,
            ],
            'day_stats' => $dayStats,
            'hour_stats' => $hourStats,
            'timeline' => $timeline,
        ]);
    }

    // 💳 Récupérer les infos de paiement
    public function getPaymentInfo()
    {
        $user = Auth::user();
        $user->load('driverDetail');

        if (!$user->driverDetail) {
            return response()->json([
                'message' => 'Détails chauffeur non trouvés'
            ], 404);
        }

        // Calcul du prochain paiement (chaque lundi)
        $now = now();
        $nextPaymentDate = $now->copy()->startOfWeek()->next('Monday')->startOfDay();
        
        // Si nous sommes déjà lundi, le prochain paiement est le lundi suivant
        if ($now->isMonday() && $now->hour >= 0) {
            $nextPaymentDate = $nextPaymentDate->addWeek();
        }

        // Calcul des gains en attente (courses de la semaine précédente)
        $weekStartDate = $now->copy()->subWeek()->startOfWeek();
        $weekEndDate = $now->copy()->subWeek()->endOfWeek();

        // Récupérer la commission plateforme pour le calcul des gains
        $platformCommissionPercent = (float) Setting::getValue('platform_commission_percent', 20);
        $commissionMultiplier = (100 - $platformCommissionPercent) / 100;

        $pendingEarnings = $user->driverRides()
            ->whereBetween('rides.created_at', [$weekStartDate, $weekEndDate])
            ->where('rides.status', 'completed')
            ->join('payments', 'rides.id', '=', 'payments.ride_id')
            ->where('payments.status', 'completed')
            ->sum(\DB::raw('payments.amount * '.$commissionMultiplier));

        // Dernier paiement
        $lastPayment = $user->driverDetail->last_payment_date;

        return response()->json([
            'payment_method' => $user->driverDetail->payment_method ?? 'bank_transfer',
            'bank_account_holder' => $user->driverDetail->bank_account_holder,
            'bank_iban' => $user->driverDetail->bank_iban ? substr($user->driverDetail->bank_iban, -4) : null, // Masquer sauf les 4 derniers chiffres
            'bank_bic' => $user->driverDetail->bank_bic,
            'paypal_email' => $user->driverDetail->paypal_email,
            'stripe_id' => $user->driverDetail->stripe_id,
            'last_payment_date' => $lastPayment,
            'next_payment_date' => $nextPaymentDate->toDateTimeString(),
            'next_payment_date_formatted' => $nextPaymentDate->format('d/m/Y'),
            'pending_earnings' => (float) $pendingEarnings,
            'pending_balance' => (float) ($user->driverDetail->pending_balance ?? 0),
            'total_earnings' => (float) ($user->driverDetail->total_earnings ?? 0),
            'is_configured' => !empty($user->driverDetail->payment_method),
            'payment_methods_available' => [
                'bank_transfer' => 'Virement bancaire',
                'check' => 'Chèque',
                'cash' => 'Espèces',
                'paypal' => 'PayPal',
                'stripe' => 'Stripe'
            ]
        ]);
    }

    // Uploader la photo de profil
    public function uploadProfilePicture(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        // Supprimer l'ancienne photo si elle existe
        if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        // Sauvegarder la nouvelle photo
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $path = $file->store('profile-pictures/' . $user->id, 'public');

            // Mettre à jour la base de données avec le chemin
            $user->update([
                'profile_picture' => $path,
            ]);

            // Recharger l'utilisateur depuis la base de données
            $user = Auth::user();
            
            // Load driver details
            if ($user->isDriver()) {
                $user->load('driverDetail');
            }
            
            $photoUrl = Storage::url($path);

            return response()->json([
                'message' => 'Photo de profil mise à jour avec succès',
                'profile_picture' => $photoUrl,
                'profile_picture_path' => $path,
                'user' => $user
            ]);
        }

        return response()->json([
            'message' => 'Erreur lors du téléchargement de la photo'
        ], 422);
    }

    // 💳 NOUVEAU: Historique des paiements du chauffeur
    public function paymentHistory(Request $request)
    {
        $user = Auth::user();

        // Récupérer l'historique des paiements avec pagination
        $payments = \App\Models\DriverPayment::forDriver($user->id)
            ->orderBy('paid_at', 'desc')
            ->paginate(10);

        // Transformer les paiements
        $transformedPayments = $payments->map(function ($payment) {
            // Extraire la référence de proof_data
            $proofReference = '---';
            if ($payment->proof_data) {
                $proofReference = $payment->proof_data['reference'] ?? '---';
            }

            // Période: du lundi au dimanche de la semaine du paiement
            // Le paiement est effectué le lundi pour les gains de la semaine précédente
            // Donc: paid_at (lundi) - 1 jour = dimanche de fin, puis chercher le lundi du début
            $periodEnd = $payment->paid_at?->copy()->subDay(); // Dimanche
            $periodStart = $periodEnd?->copy()->startOfWeek(); // Lundi de cette semaine

            $periodStartFormatted = $periodStart?->format('d/m/Y') ?? '---';
            $periodEndFormatted = $periodEnd?->format('d/m/Y') ?? '---';

            return [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toDateTimeString(),
                'paid_at_formatted' => $payment->paid_at?->format('d/m/Y H:i'),
                'period_start_formatted' => $periodStartFormatted,
                'period_end_formatted' => $periodEndFormatted,
                'verified_at' => $payment->verified_at?->toDateTimeString(),
                'admin_notes' => $payment->admin_notes,
                'proof_reference' => $proofReference,
            ];
        });

        return response()->json([
            'message' => 'Historique des paiements',
            'payments' => [
                'data' => $transformedPayments->all(),
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ]
        ]);
    }

    // 💳 Récupérer les statistiques de paiement du chauffeur
    public function paymentStats(Request $request)
    {
        $user = Auth::user();

        $totalPaid = \App\Models\DriverPayment::forDriver($user->id)
            ->completed()
            ->sum('amount');

        $lastPayment = \App\Models\DriverPayment::forDriver($user->id)
            ->completed()
            ->latest('paid_at')
            ->first();

        $nextPaymentDate = null;
        $nextPaymentDateFormatted = null;
        if ($lastPayment) {
            // Le prochain paiement est le lundi suivant
            $nextPaymentDate = $lastPayment->paid_at->next(\Illuminate\Support\Carbon::MONDAY);
            // Format simple jj/mm/aaaa
            $nextPaymentDateFormatted = $nextPaymentDate->format('d/m/Y');
        }

        return response()->json([
            'message' => 'Statistiques de paiement',
            'stats' => [
                'total_paid' => (float) $totalPaid,
                'total_paid_formatted' => number_format($totalPaid, 2, ',', ' ') . '€',
                'total_payments' => \App\Models\DriverPayment::forDriver($user->id)->completed()->count(),
                'last_payment' => $lastPayment ? [
                    'amount' => (float) $lastPayment->amount,
                    'date' => $lastPayment->paid_at->toDateTimeString(),
                    'date_formatted' => $lastPayment->paid_at->format('d/m/Y'),
                    'method' => $lastPayment->payment_method,
                ] : null,
                'next_payment_date' => $nextPaymentDate?->toDateTimeString(),
                'next_payment_date_formatted' => $nextPaymentDateFormatted,
            ]
        ]);
    }
}
