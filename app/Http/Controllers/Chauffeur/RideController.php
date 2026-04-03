<?php

namespace App\Http\Controllers\Chauffeur;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RideController extends Controller
{
    // Courses disponibles
    public function available(Request $request)
    {
        $driver = Auth::user();

        // Vérifier si le chauffeur est disponible
        if (!$driver->driverDetail || !$driver->driverDetail->is_available) {
            return response()->json([
                'message' => 'Vous devez être disponible pour voir les courses'
            ], 400);
        }

        // 🔴 IMPORTANT: Récupérer SEULEMENT les courses avec acompte payé
        // Les courses sans acompte payé ne sont PAS visibles aux chauffeurs
        // ⚠️ RETOURNER TOUTES LES COURSES SANS PAGINATION
        $rides = Ride::where('status', 'pending')
            ->where('deposit_status', 'paid') // 🔴 ACOMPTE OBLIGATOIREMENT PAYÉ
            ->where(function ($query) use ($driver) {
                // Exclure les courses que le chauffeur a déjà refusées
                // (À implémenter si vous avez un système de refus)
            })
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->get(); // 🔴 IMPORTANT: .get() au lieu de .paginate() pour récupérer TOUTES les courses

        return response()->json([
            'message' => 'Courses disponibles récupérées',
            'data' => $rides,
            'total' => $rides->count(),
        ]);
    }

    // Historique des courses du chauffeur
    public function index(Request $request)
    {
        $driver = Auth::user();
        
        // DEBUG: Log les infos du chauffeur
        Log::info('🚗 [CHAUFFEUR_RIDES_CONTROLLER] Requête pour les courses du chauffeur', [
            'driver_id' => $driver->id,
            'driver_email' => $driver->email,
            'driver_role' => $driver->role,
        ]);

        // 🔴 IMPORTANT: Récupérer TOUTES les courses sans filtres
        // 1. Les courses assignées au chauffeur connecté (driver_id = current_driver)
        // 2. Les courses disponibles (status = pending ET driver_id = NULL, avec deposit_status = 'paid')
        $rides = Ride::where(function ($query) use ($driver) {
            $query->where('driver_id', $driver->id)  // Mes courses
                  ->orWhere(function ($subQuery) {
                      // Les courses disponibles à accepter
                      $subQuery->where('status', 'pending')
                               ->whereNull('driver_id')
                               ->where('deposit_status', 'paid');
                  });
        })
        ->with(['client', 'driver'])
        ->orderBy('created_at', 'desc')
        ->get();
        
        // DEBUG: Log les résultats
        Log::info('🚗 [CHAUFFEUR_RIDES_CONTROLLER] Total rides retrieved: ' . $rides->count());
        if (count($rides) > 0) {
            Log::info('🚗 [CHAUFFEUR_RIDES_CONTROLLER] Rides breakdown:');
            Log::info('  - Assigned to me: ' . $rides->where('driver_id', $driver->id)->count());
            Log::info('  - Available to accept: ' . $rides->where('driver_id', null)->count());
        }

        // Ajouter les statistiques pour la période
        $stats = [
            'total_earnings' => Ride::where('driver_id', $driver->id)->where('status', 'completed')->sum('driver_earnings'),
            'completed_rides' => Ride::where('driver_id', $driver->id)->where('status', 'completed')->count(),
            'cancelled_rides' => Ride::where('driver_id', $driver->id)->where('status', 'cancelled')->count(),
        ];

        return response()->json([
            'message' => 'Courses du chauffeur récupérées',
            'rides' => [
                'data' => $rides->toArray(),
                'total' => $rides->count(),
            ],
            'stats' => $stats,
        ]);
    }

    // Détails d'une course
    public function show(Ride $ride)
    {
        $driver = Auth::user();

        // Vérifier que la course appartient au chauffeur
        if ($ride->driver_id !== $driver->id) {
            return response()->json([
                'message' => 'Accès non autorisé à cette course'
            ], 403);
        }

        $ride->load(['client', 'payment']);
        return response()->json($ride);
    }

    // Accepter une course
    public function accept(Request $request, Ride $ride)
    {
        $driver = Auth::user();

        // Vérifications
        if ($ride->status !== 'pending') {
            return response()->json([
                'message' => 'Cette course n\'est plus disponible'
            ], 400);
        }

        if (!$driver->driverDetail || !$driver->driverDetail->is_available) {
            return response()->json([
                'message' => 'Vous devez être disponible pour accepter une course'
            ], 400);
        }

        // Mettre à jour la course
        $ride->update([
            'driver_id' => $driver->id,
            'status' => 'assigned',
            'accepted_at' => now(),
        ]);

        // Mettre le chauffeur comme occupé
        // $driver->driverDetail->update(['is_available' => false]);

        // TODO: Envoyer une notification au client

        return response()->json([
            'message' => 'Course acceptée avec succès',
            'ride' => $ride->load('client')
        ]);
    }

    // Refuser une course (après acceptation, avant de démarrer)
    public function refuse(Request $request, Ride $ride)
    {
        $driver = Auth::user();

        // Vérifications
        if ($ride->driver_id !== $driver->id) {
            return response()->json([
                'message' => 'Accès non autorisé'
            ], 403);
        }

        if ($ride->status !== 'pending') {
            return response()->json([
                'message' => 'Cette course ne peut pas être refusée'
            ], 400);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Remettre la course à l'état pending (driver_id = null pour un autre chauffeur)
        $ride->update([
            'driver_id' => null,
            'status' => 'pending',
            'accepted_at' => null,
        ]);

        // Redevenir disponible
        // $driver->driverDetail->update(['is_available' => true]);

        // TODO: Envoyer une notification au client

        return response()->json([
            'message' => 'Course refusée',
            'ride' => $ride->load('client')
        ]);
    }

    // Démarrer une course
    public function start(Request $request, Ride $ride)
    {
        $driver = Auth::user();

        // Vérifications
        if ($ride->driver_id !== $driver->id) {
            return response()->json([
                'message' => 'Accès non autorisé'
            ], 403);
        }

        if ($ride->status !== 'assigned') {
            return response()->json([
                'message' => 'Cette course ne peut pas être démarrée'
            ], 400);
        }

        $ride->update([
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        // TODO: Envoyer une notification au client

        return response()->json([
            'message' => 'Course démarrée',
            'ride' => $ride
        ]);
    }

    // Terminer une course
    public function complete(Request $request, Ride $ride)
    {
        $driver = Auth::user();

        // Vérifications
        if ($ride->driver_id !== $driver->id) {
            return response()->json([
                'message' => 'Accès non autorisé'
            ], 403);
        }

        if ($ride->status !== 'ongoing') {
            return response()->json([
                'message' => 'Cette course ne peut pas être terminée'
            ], 400);
        }

        $request->validate([
            'final_price' => 'required|numeric|min:0',
            'distance_km' => 'required|numeric|min:0',
        ]);

        // Mettre à jour la course
        $ride->update([
            'status' => 'completed',
            'completed_at' => now(),
            'final_price' => $request->final_price,
            'distance_km' => $request->distance_km,
        ]);

        // Calculer la commission et les gains
        $ride->calculateCommission();

        // Mettre à jour les statistiques du chauffeur
        // 🔴 IMPORTANT: Ne PAS réajouter les earnings ici car ils sont déjà ajoutés
        // dans Client/RideController.php quand le client complète le paiement
        $driverDetail = $driver->driverDetail;
        $driverDetail->completed_rides += 1;
        $driverDetail->is_available = true; // Redevenir disponible
        $driverDetail->save();

        // Mettre à jour les statistiques du client
        $client = $ride->client;
        $client->total_spent += $ride->final_price;
        $client->ride_count += 1;
        $client->save();

        // TODO: Créer un enregistrement de paiement
        // TODO: Envoyer une notification au client avec le reçu

        return response()->json([
            'message' => 'Course terminée avec succès',
            'ride' => $ride->load(['client', 'payment']),
            'driver_earnings' => $ride->driver_earnings,
        ]);
    }

    // Annuler une course
    public function cancel(Request $request, Ride $ride)
    {
        $driver = Auth::user();

        // Vérifications
        if ($ride->driver_id !== $driver->id) {
            return response()->json([
                'message' => 'Accès non autorisé'
            ], 403);
        }

        if (!in_array($ride->status, ['assigned', 'ongoing'])) {
            return response()->json([
                'message' => 'Cette course ne peut pas être annulée'
            ], 400);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $ride->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => 'driver',
            'cancellation_reason' => $request->reason,
        ]);

        // Redevenir disponible
        $driver->driverDetail->update(['is_available' => true]);

        // TODO: Envoyer une notification au client
        // TODO: Notifier l'admin si annulation fréquente

        return response()->json([
            'message' => 'Course annulée',
            'ride' => $ride
        ]);
    }
}
