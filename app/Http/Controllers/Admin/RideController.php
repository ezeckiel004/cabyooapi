<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use Illuminate\Http\Request;

class RideController extends Controller
{
    // Liste toutes les courses
    public function index(Request $request)
    {
        $query = Ride::with(['client', 'driver']);

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ride_number', 'like', "%$search%")
                    ->orWhere('pickup_address', 'like', "%$search%")
                    ->orWhere('dropoff_address', 'like', "%$search%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%");
                    })
                    ->orWhereHas('driver', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    });
            });
        }

        $rides = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($rides);
    }

    // Détails d'une course
    public function show(Ride $ride)
    {
        $ride->load(['client', 'driver', 'payment']);
        return response()->json($ride);
    }

    // Mettre à jour une course (informations seulement)
    public function update(Request $request, Ride $ride)
    {
        $validated = $request->validate([
            'pickup_address' => 'sometimes|string|max:500',
            'dropoff_address' => 'sometimes|string|max:500',
            'estimated_price' => 'sometimes|numeric|min:0',
            'final_price' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $ride->update($validated);

        // Recalculer la commission si le prix final change
        if ($request->has('final_price') && $ride->status === 'completed') {
            $ride->calculateCommission();
        }

        return response()->json([
            'message' => 'Course mise à jour avec succès',
            'ride' => $ride->load(['client', 'driver'])
        ]);
    }

    // 🔴 NOUVEAU: Assigner un chauffeur/véhicule à une course programmée
    public function assign(Request $request, Ride $ride)
    {
        // Vérifier que c'est une course en attente
        if ($ride->status !== 'pending') {
            return response()->json([
                'message' => 'Cette course ne peut pas être assignée (elle doit être en statut "pending")',
                'current_status' => $ride->status
            ], 400);
        }

        $validated = $request->validate([
            'driver_id' => 'required|integer|exists:users,id',
        ]);

        // Vérifier que l'utilisateur est bien un chauffeur
        $driver = \App\Models\User::findOrFail($validated['driver_id']);
        if ($driver->role !== 'chauffeur') {
            return response()->json([
                'message' => 'L\'utilisateur sélectionné n\'est pas un chauffeur'
            ], 400);
        }

        // Assigner le chauffeur et mettre à jour le statut
        $ride->update([
            'driver_id' => $validated['driver_id'],
            'status' => 'assigned'
        ]);

        return response()->json([
            'message' => 'Chauffeur assigné avec succès',
            'ride' => $ride->load(['client', 'driver'])
        ]);
    }

    // Statistiques des courses
    public function stats(Request $request)
    {
        $period = $request->get('period', 'today'); // today, week, month, year

        $query = Ride::query();
        $completedQuery = Ride::where('status', 'completed');

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                $completedQuery->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                $completedQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month);
                $completedQuery->whereMonth('created_at', now()->month);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                $completedQuery->whereYear('created_at', now()->year);
                break;
        }

        $totalRides = $query->count();
        $completedRides = $completedQuery->count();
        $cancelledRides = $query->clone()->where('status', 'cancelled')->count();
        $pendingRides = $query->clone()->where('status', 'pending')->count();

        // LES DEUX TYPES DE REVENUS
        $estimatedRevenue = $query->sum('estimated_price');
        $completedRevenue = $completedQuery->sum('final_price');
        $platformRevenue = $completedQuery->sum('platform_commission');

        $averageEstimatedValue = $totalRides > 0 ? $estimatedRevenue / $totalRides : 0;
        $averageCompletedValue = $completedRides > 0 ? $completedRevenue / $completedRides : 0;

        $cancellationRate = $totalRides > 0 ? ($cancelledRides / $totalRides) * 100 : 0;

        return response()->json([
            'period' => $period,
            'total_rides' => $totalRides,
            'completed_rides' => $completedRides,
            'cancelled_rides' => $cancelledRides,
            'pending_rides' => $pendingRides,
            'estimated_revenue' => (float) $estimatedRevenue,
            'completed_revenue' => (float) $completedRevenue,
            'platform_revenue' => (float) $platformRevenue,
            'average_estimated_value' => round($averageEstimatedValue, 2),
            'average_completed_value' => round($averageCompletedValue, 2),
            'cancellation_rate' => round($cancellationRate, 2),
        ]);
    }
}
