<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{
    /**
     * Update driver's current location
     * 
     * POST /api/driver/location
     * 
     * @body {
     *   "latitude": 48.8566,
     *   "longitude": 2.3522,
     *   "accuracy": 10.5
     * }
     */
    public function updateLocation(Request $request)
    {
        try {
            // Valider les données
            $validated = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'accuracy' => 'nullable|numeric',
            ]);

            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié',
                ], 401);
            }

            // Vérifier que c'est un chauffeur
            if ($user->role !== 'chauffeur') {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas un chauffeur',
                ], 403);
            }

            // Mettre à jour la position du chauffeur
            $driverDetail = $user->driverDetail;
            
            if (!$driverDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Détails du chauffeur non trouvés',
                ], 404);
            }

            Log::info("🔄 [LocationController] Avant update", [
                'driver_id' => $user->id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'accuracy' => $validated['accuracy'] ?? null,
            ]);

            // Mettre à jour directement en BD
            $updated = $driverDetail->update([
                'current_latitude' => $validated['latitude'],
                'current_longitude' => $validated['longitude'],
                'last_location_update' => now(),
                'location_enabled' => true,
            ]);

            Log::info("📍 [LocationController] Update result: " . ($updated ? 'True' : 'False'));

            // Recharger les données depuis la BD
            $driverDetail->refresh();

            Log::info("✅ [LocationController] Position du chauffeur mise à jour", [
                'driver_id' => $user->id,
                'current_latitude' => $driverDetail->current_latitude,
                'current_longitude' => $driverDetail->current_longitude,
                'last_location_update' => $driverDetail->last_location_update,
                'location_enabled' => $driverDetail->location_enabled,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Position mise à jour avec succès',
                'data' => [
                    'driver_id' => $user->id,
                    'latitude' => $driverDetail->current_latitude,
                    'longitude' => $driverDetail->current_longitude,
                    'last_update' => $driverDetail->last_location_update,
                    'location_enabled' => $driverDetail->location_enabled,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error("❌ Erreur lors de la mise à jour de la position", [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la position',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current location of authenticated driver
     * 
     * GET /api/driver/location
     */
    public function getLocation()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié',
                ], 401);
            }

            if ($user->role !== 'chauffeur') {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas un chauffeur',
                ], 403);
            }

            $driverDetail = $user->driverDetail;
            
            if (!$driverDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Détails du chauffeur non trouvés',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'driver_id' => $user->id,
                    'driver_name' => $user->name,
                    'latitude' => $driverDetail->current_latitude,
                    'longitude' => $driverDetail->current_longitude,
                    'last_update' => $driverDetail->last_location_update,
                    'location_enabled' => $driverDetail->location_enabled,
                    'is_available' => $driverDetail->is_available,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error("❌ Erreur lors de la récupération de la position", [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la position',
            ], 500);
        }
    }

    /**
     * Get all active drivers' locations (for admin/client map)
     * 
     * GET /api/drivers/locations
     */
    public function getAllDriversLocations(Request $request)
    {
        try {
            $query = \App\Models\User::where('role', 'chauffeur')
                ->with('driverDetail')
                ->whereHas('driverDetail', function ($q) {
                    // Seulement les chauffeurs avec location_enabled et une position récente (moins de 5 minutes)
                    $q->where('location_enabled', true)
                      ->where('last_location_update', '>=', now()->subMinutes(5))
                      ->whereNotNull('current_latitude')
                      ->whereNotNull('current_longitude');
                });

            // Optionnel: filtrer par disponibilité
            if ($request->get('available') === 'true') {
                $query->whereHas('driverDetail', function ($q) {
                    $q->where('is_available', true);
                });
            }

            $drivers = $query->get();

            $driversData = $drivers->map(function ($driver) {
                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'phone' => $driver->phone,
                    'latitude' => $driver->driverDetail->current_latitude,
                    'longitude' => $driver->driverDetail->current_longitude,
                    'last_update' => $driver->driverDetail->last_location_update,
                    'is_available' => $driver->driverDetail->is_available,
                    'car_type' => $driver->driverDetail->car_type,
                    'car_plate' => $driver->driverDetail->car_plate,
                    'rating' => $driver->driverDetail->rating,
                ];
            });

            return response()->json([
                'success' => true,
                'count' => $driversData->count(),
                'data' => $driversData,
            ], 200);

        } catch (\Exception $e) {
            Log::error("❌ Erreur lors de la récupération des positions des chauffeurs", [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des positions',
            ], 500);
        }
    }

    /**
     * Disable location tracking for driver
     * 
     * POST /api/driver/location/disable
     */
    public function disableLocation(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié',
                ], 401);
            }

            if ($user->role !== 'chauffeur') {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas un chauffeur',
                ], 403);
            }

            $driverDetail = $user->driverDetail;
            
            if (!$driverDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Détails du chauffeur non trouvés',
                ], 404);
            }

            $driverDetail->update([
                'location_enabled' => false,
            ]);

            Log::info("📍 Suivi de position désactivé", [
                'driver_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Suivi de position désactivé',
            ], 200);

        } catch (\Exception $e) {
            Log::error("❌ Erreur lors de la désactivation du suivi", [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la désactivation',
            ], 500);
        }
    }
}
