<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // Liste tous les utilisateurs
    public function index(Request $request)
    {
        $query = User::query();

        // Filtres
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($users);
    }

    // Liste des clients
    public function clients(Request $request)
    {
        $clients = User::clients()
            ->withCount(['clientRides as pending_rides' => function ($query) {
                $query->whereIn('status', ['pending', 'assigned', 'ongoing']);
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($clients);
    }

    // Liste des chauffeurs
    public function drivers(Request $request)
    {
        $query = User::drivers()->with('driverDetail');

        // Filtre par statut
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $drivers = $query->withCount(['driverRides as completed_rides_count' => function ($query) {
            $query->where('status', 'completed');
        }])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($drivers);
    }

    // Liste des chauffeurs en attente de validation
    public function pendingDrivers(Request $request)
    {
        $query = User::drivers()
            ->where('status', 'inactive')
            ->with('driverDetail');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhereHas('driverDetail', function ($q) use ($search) {
                        $q->where('car_plate', 'like', "%$search%")
                            ->orWhere('driver_license', 'like', "%$search%");
                    });
            });
        }

        $pendingDrivers = $query->orderBy('created_at', 'desc')->paginate(20);

        // Ajouter des statistiques
        $pendingDrivers->getCollection()->transform(function ($driver) {
            $driver->days_waiting = now()->diffInDays($driver->created_at);
            $driver->documents_complete = !empty($driver->driverDetail->driver_license)
                && !empty($driver->driverDetail->car_plate)
                && !empty($driver->driverDetail->car_model);
            return $driver;
        });

        return response()->json([
            'drivers' => $pendingDrivers,
            'stats' => [
                'total_pending' => User::drivers()->where('status', 'inactive')->count(),
                'avg_waiting_days' => User::drivers()->where('status', 'inactive')
                    ->avg(DB::raw('DATEDIFF(NOW(), created_at)')) ?? 0,
            ]
        ]);
    }

    // Approuver un chauffeur en attente
    public function approveDriver(Request $request, User $user)
    {
        if ($user->role !== 'chauffeur') {
            return response()->json([
                'message' => 'Cet utilisateur n\'est pas un chauffeur'
            ], 400);
        }

        if ($user->status !== 'inactive') {
            return response()->json([
                'message' => 'Ce chauffeur n\'est pas en attente de validation'
            ], 400);
        }

        $request->validate([
            'notes' => 'nullable|string|max:500',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $user->update([
            'status' => 'active',
            'notes' => $request->notes
                ? ($user->notes ? $user->notes . "\n" : '') . date('Y-m-d H:i') . " - Compte approuvé par admin. " . $request->notes
                : $user->notes
        ]);

        // Activer la disponibilité
        if ($user->driverDetail) {
            $user->driverDetail->update([
                'is_available' => true,
                'commission_rate' => $request->commission_rate ?? config('settings.platform_commission_percent', 20)
            ]);
        }

        // TODO: Envoyer une notification/email au chauffeur
        // TODO: Envoyer un SMS de confirmation

        return response()->json([
            'message' => 'Chauffeur approuvé avec succès',
            'user' => $user->load('driverDetail')
        ]);
    }

    // Rejeter un chauffeur en attente
    public function rejectDriver(Request $request, User $user)
    {
        if ($user->role !== 'chauffeur') {
            return response()->json([
                'message' => 'Cet utilisateur n\'est pas un chauffeur'
            ], 400);
        }

        if ($user->status !== 'inactive') {
            return response()->json([
                'message' => 'Ce chauffeur n\'est pas en attente de validation'
            ], 400);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
            'permanent_block' => 'nullable|boolean',
        ]);

        $newStatus = $request->permanent_block ? 'blocked' : 'inactive';

        $user->update([
            'status' => $newStatus,
            'notes' => ($user->notes ? $user->notes . "\n" : '') . date('Y-m-d H:i') . " - Compte rejeté. Raison: " . $request->reason
        ]);

        // Désactiver la disponibilité
        if ($user->driverDetail) {
            $user->driverDetail->update(['is_available' => false]);
        }

        // TODO: Envoyer une notification/email au chauffeur avec la raison
        // TODO: Supprimer les documents uploadés si nécessaire

        return response()->json([
            'message' => 'Chauffeur rejeté',
            'user' => $user,
            'permanent_block' => $request->permanent_block ?? false
        ]);
    }

    // Détails d'un utilisateur
    public function show(User $user)
    {
        $user->load(['driverDetail', 'clientRides' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }]);

        // Ajouter les statistiques
        if ($user->isClient()) {
            $user->total_spent = $user->clientRides()
                ->where('status', 'completed')
                ->sum('final_price');

            $user->ride_count = $user->clientRides()->count();
            $user->canceled_count = $user->clientRides()
                ->where('status', 'cancelled')
                ->count();

            // Fréquence d'utilisation
            $firstRide = $user->clientRides()->orderBy('created_at')->first();
            if ($firstRide) {
                $daysSinceFirstRide = now()->diffInDays($firstRide->created_at);
                $user->usage_frequency = $daysSinceFirstRide > 0
                    ? round($user->ride_count / $daysSinceFirstRide, 2)
                    : $user->ride_count;
            } else {
                $user->usage_frequency = 0;
            }
        }

        if ($user->isDriver()) {
            $user->load('driverDetail');
            $user->total_earnings = $user->driverRides()
                ->where('status', 'completed')
                ->sum('driver_earnings');

            $user->completed_rides = $user->driverRides()
                ->where('status', 'completed')
                ->count();

            $user->acceptance_rate = $user->driverRides()->count() > 0
                ? round(($user->driverRides()->whereNotNull('driver_id')->count() / $user->driverRides()->count()) * 100, 2)
                : 0;

            // Calculer le taux d'annulation
            $user->cancellation_rate = $user->driverRides()->count() > 0
                ? round(($user->driverRides()->where('status', 'cancelled')->where('cancelled_by', 'driver')->count() / $user->driverRides()->count()) * 100, 2)
                : 0;

            // Périodes d'activité
            $activityPeriods = $user->driverRides()
                ->selectRaw('DATE(created_at) as date, COUNT(*) as rides')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get();

            $user->activity_periods = $activityPeriods;
        }

        // Notes internes
        $user->internal_notes = $user->notes;

        return response()->json($user);
    }

    // Mettre à jour un utilisateur
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['sometimes', 'string', Rule::unique('users')->ignore($user->id)],
            'status' => 'sometimes|in:active,inactive,blocked,on_leave',
            'notes' => 'nullable|string',
        ]);

        $user->update($validated);

        // Mettre à jour les détails chauffeur si nécessaire
        if ($user->isDriver() && $request->has('driver_details')) {
            $user->driverDetail()->updateOrCreate(
                ['user_id' => $user->id],
                $request->driver_details
            );
        }

        return response()->json([
            'message' => 'Utilisateur mis à jour avec succès',
            'user' => $user->load('driverDetail')
        ]);
    }

    // Mettre à jour le statut
    public function updateStatus(Request $request, User $user)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,blocked,on_leave',
            'reason' => 'nullable|string|max:500',
        ]);

        $oldStatus = $user->status;
        $user->update([
            'status' => $request->status,
            'notes' => $request->notes
                ? ($user->notes ? $user->notes . "\n" : '') . date('Y-m-d H:i') . " - Statut changé de $oldStatus à {$request->status}. Raison: {$request->reason}"
                : $user->notes
        ]);

        // Si c'est un chauffeur et qu'on le désactive, mettre à jour la disponibilité
        if ($user->isDriver() && $user->driverDetail) {
            $isAvailable = $request->status === 'active';
            $user->driverDetail->update(['is_available' => $isAvailable]);
        }

        return response()->json([
            'message' => 'Statut mis à jour avec succès',
            'user' => $user
        ]);
    }

    // Changer le rôle
    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|in:client,chauffeur,admin',
        ]);

        $oldRole = $user->role;
        $user->update(['role' => $request->role]);

        // Si changement en chauffeur, créer les détails si inexistants
        if ($request->role === 'chauffeur' && !$user->driverDetail) {
            $user->driverDetail()->create([
                'is_available' => $user->status === 'active',
            ]);
        }

        // Si changement depuis chauffeur, supprimer les détails
        if ($oldRole === 'chauffeur' && $request->role !== 'chauffeur') {
            $user->driverDetail()->delete();
        }

        return response()->json([
            'message' => 'Rôle mis à jour avec succès',
            'user' => $user->load('driverDetail')
        ]);
    }

    // Supprimer un utilisateur (soft delete)
    public function destroy(User $user)
    {
        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Impossible de supprimer un administrateur'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'Utilisateur supprimé avec succès'
        ]);
    }

    // Historique des courses d'un utilisateur
    public function userRides(Request $request, User $user)
    {
        $query = Ride::query();

        if ($user->isClient()) {
            $query->where('client_id', $user->id);
        } elseif ($user->isDriver()) {
            $query->where('driver_id', $user->id);
        } else {
            return response()->json(['message' => 'Utilisateur non valide'], 400);
        }

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $rides = $query->with(['client', 'driver'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Ajouter des statistiques pour la période
        $stats = [
            'total_rides' => $query->clone()->count(),
            'completed_rides' => $query->clone()->where('status', 'completed')->count(),
            'cancelled_rides' => $query->clone()->where('status', 'cancelled')->count(),
            'total_amount' => $user->isClient()
                ? $query->clone()->where('status', 'completed')->sum('final_price')
                : $query->clone()->where('status', 'completed')->sum('driver_earnings'),
        ];

        return response()->json([
            'rides' => $rides,
            'stats' => $stats,
            'user' => [
                'name' => $user->name,
                'role' => $user->role,
                'status' => $user->status,
            ]
        ]);
    }

    // Recherche avancée d'utilisateurs
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'role' => 'nullable|in:client,chauffeur,admin',
            'status' => 'nullable|in:active,inactive,blocked,on_leave',
        ]);

        $query = User::query();

        // Recherche par nom, email, téléphone
        $search = $request->query;
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%");
        });

        // Filtres additionnels
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->with(['driverDetail'])
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json($users);
    }

    // Exporter la liste des utilisateurs
    public function export(Request $request)
    {
        $request->validate([
            'type' => 'required|in:clients,drivers,all',
            'format' => 'required|in:json,csv',
            'status' => 'nullable|in:active,inactive,blocked,on_leave',
        ]);

        $query = User::query();

        if ($request->type === 'clients') {
            $query->where('role', 'client');
        } elseif ($request->type === 'drivers') {
            $query->where('role', 'chauffeur')->with('driverDetail');
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        // Formater les données pour l'export
        $exportData = $users->map(function ($user) {
            $data = [
                'ID' => $user->id,
                'Nom' => $user->name,
                'Email' => $user->email,
                'Téléphone' => $user->phone,
                'Rôle' => $user->role,
                'Statut' => $user->status,
                'Date d\'inscription' => $user->created_at->format('Y-m-d H:i'),
            ];

            if ($user->isClient()) {
                $data['Courses effectuées'] = $user->clientRides()->count();
                $data['Montant total dépensé'] = $user->clientRides()->where('status', 'completed')->sum('final_price');
            }

            if ($user->isDriver() && $user->driverDetail) {
                $data['Permis de conduire'] = $user->driverDetail->driver_license;
                $data['Modèle voiture'] = $user->driverDetail->car_model;
                $data['Plaque d\'immatriculation'] = $user->driverDetail->car_plate;
                $data['Couleur voiture'] = $user->driverDetail->car_color;
                $data['Expérience (années)'] = $user->driverDetail->year_of_experience;
                $data['Courses complétées'] = $user->driverDetail->completed_rides;
                $data['Revenus totaux'] = $user->driverDetail->total_earnings;
                $data['Note'] = $user->driverDetail->rating;
                $data['Disponible'] = $user->driverDetail->is_available ? 'Oui' : 'Non';
            }

            return $data;
        });

        if ($request->format === 'csv') {
            // TODO: Implémenter l'export CSV
            return response()->json([
                'message' => 'Export CSV à implémenter',
                'data' => $exportData
            ]);
        }

        return response()->json([
            'message' => 'Export réussi',
            'type' => $request->type,
            'total' => $users->count(),
            'data' => $exportData
        ]);
    }

    // 💳 NOUVEAU: Initier un paiement pour un chauffeur
    public function initiateDriverPayment(Request $request, User $user)
    {
        // Vérifier que c'est un chauffeur
        if ($user->role !== 'chauffeur') {
            return response()->json([
                'message' => 'Cet utilisateur n\'est pas un chauffeur'
            ], 400);
        }

        try {
            $amount = $user->driverDetail?->total_earnings ?? 0;

            if ($amount <= 0) {
                return response()->json([
                    'message' => 'Ce chauffeur n\'a pas de gains en attente'
                ], 400);
            }

            // Retourner les détails du paiement
            return response()->json([
                'message' => 'Détails de paiement récupérés',
                'payment' => [
                    'driver_id' => $user->id,
                    'driver_name' => $user->name,
                    'driver_email' => $user->email,
                    'amount' => (float) $amount,
                    'payment_method' => $user->driverDetail->payment_method ?? 'bank_transfer',
                    'bank_account_holder' => $user->driverDetail->bank_account_holder,
                    'bank_iban' => $user->driverDetail->bank_iban,
                    'bank_bic' => $user->driverDetail->bank_bic,
                    'paypal_email' => $user->driverDetail->paypal_email,
                    'stripe_id' => $user->driverDetail->stripe_id,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    // 💳 Enregistrer et confirmer le paiement
    public function confirmDriverPayment(Request $request, User $user)
    {
        // Vérifier que c'est un chauffeur
        if ($user->role !== 'chauffeur') {
            return response()->json([
                'message' => 'Cet utilisateur n\'est pas un chauffeur'
            ], 400);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:bank_transfer,paypal,stripe',
            'proof_data' => 'nullable|array', // Données dynamiques selon la méthode
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $amount = $user->driverDetail?->total_earnings ?? 0;

            if ($amount <= 0) {
                return response()->json([
                    'message' => 'Ce chauffeur n\'a pas de gains en attente'
                ], 400);
            }

            // Créer l'enregistrement de paiement
            $payment = \App\Models\DriverPayment::create([
                'driver_id' => $user->id,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
                'bank_account_holder' => $user->driverDetail->bank_account_holder,
                'bank_iban' => $user->driverDetail->bank_iban,
                'bank_bic' => $user->driverDetail->bank_bic,
                'paypal_email' => $user->driverDetail->paypal_email,
                'stripe_id' => $user->driverDetail->stripe_id,
                'proof_data' => $validated['proof_data'] ?? null,
                'admin_notes' => $validated['admin_notes'] ?? null,
                'status' => 'pending',
            ]);

            // Marquer comme complétée et mettre à jour les earnings
            $payment->markAsPaid(auth()->id(), $validated['admin_notes'] ?? null);

            Log::info('💵 Paiement confirmé pour chauffeur', [
                'driver_id' => $user->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'method' => $validated['payment_method'],
            ]);

            return response()->json([
                'message' => 'Paiement confirmé avec succès',
                'payment' => [
                    'id' => $payment->id,
                    'driver_name' => $user->name,
                    'amount' => (float) $amount,
                    'method' => $validated['payment_method'],
                    'status' => 'completed',
                    'paid_at' => $payment->paid_at->toDateTimeString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du paiement: ' . $e->getMessage()
            ], 500);
        }
    }

    // Marquer un driver comme payé (ancienne méthode, garder pour compatibilité)
    public function payDriver(Request $request, User $user)
    {
        // Vérifier que c'est un driver
        if ($user->role !== 'driver') {
            return response()->json([
                'message' => 'Cet utilisateur n\'est pas un chauffeur'
            ], 400);
        }

        try {
            // Récupérer le total_earnings avant de le remettre à zéro
            $paidAmount = $user->driverDetail?->total_earnings ?? 0;

            // Mettre à jour le driver detail
            if ($user->driverDetail) {
                $user->driverDetail->update([
                    'total_earnings' => 0,
                    'last_payment_date' => now(),
                ]);
            }

            return response()->json([
                'message' => 'Driver payé avec succès',
                'paid_amount' => (float) $paidAmount,
                'paid_date' => now()->toDateTimeString(),
                'driver' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'total_earnings' => 0,
                    'last_payment_date' => now()->toDateTimeString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du paiement du driver: ' . $e->getMessage()
            ], 500);
        }
    }
}

