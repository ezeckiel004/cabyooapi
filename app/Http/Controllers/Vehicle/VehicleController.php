<?php

namespace App\Http\Controllers\Vehicle;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DriverDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VehicleController extends Controller
{
    /**
     * Récupérer les véhicules disponibles par type
     * 
     * GET /api/vehicles/available?car_type=berline&latitude=48.8566&longitude=2.3522
     */
    public function getAvailableByType(Request $request)
    {
        Log::info('📡 VehicleController.getAvailableByType called', [
            'car_type' => $request->car_type,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        $query = User::where('role', 'chauffeur')
            ->where('status', 'active')
            ->with('driverDetail');

        // Filtrer par type de véhicule
        if ($request->has('car_type') && !empty($request->car_type)) {
            $query->whereHas('driverDetail', function ($q) use ($request) {
                $q->where('car_type', $request->car_type)
                  ->where('is_available', true);
            });
        } else {
            // Si pas de car_type, retourner tous les disponibles
            $query->whereHas('driverDetail', function ($q) {
                $q->where('is_available', true);
            });
        }

        $drivers = $query->get();
        Log::info('✅ Found ' . $drivers->count() . ' available vehicles');

        // Format pour le client Flutter
        // Retourne un tableau avec les infos du véhicule ET du chauffeur
        $vehicles = $drivers->map(function ($driver) {
            return [
                'id' => $driver->id,
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'car_type' => $driver->driverDetail?->car_type ?? 'unknown',
                'car_model' => $driver->driverDetail?->car_model ?? 'Unknown Model',
                'car_color' => $driver->driverDetail?->car_color ?? '#000000',
                'car_plate' => $driver->driverDetail?->car_plate ?? 'XX-000-XX',
                'rating' => (float) ($driver->driverDetail?->rating ?? 0),
                'total_rides' => (int) ($driver->driverDetail?->completed_rides ?? 0),
                'is_available' => (bool) ($driver->driverDetail?->is_available ?? false),
                'base_price' => (float) ($driver->driverDetail?->base_price ?? 0),
                'phone' => $driver->phone,
                'profile_picture' => $driver->profile_picture,
            ];
        });

        // TODO: Si latitude/longitude fournis, trier par distance
        // Pour l'instant, retourner juste triés par rating

        $vehicles = $vehicles->sortByDesc('rating')->values();

        Log::info('✅ Returning ' . $vehicles->count() . ' formatted vehicles');

        return response()->json([
            'success' => true,
            'data' => $vehicles,
            'count' => $vehicles->count(),
            'car_type_requested' => $request->car_type,
        ]);
    }

    /**
     * Obtenir tous les types de véhicules disponibles
     * 
     * GET /api/vehicles/types
     */
    public function getAvailableTypes(Request $request)
    {
        Log::info('📡 VehicleController.getAvailableTypes called');

        $types = User::where('role', 'chauffeur')
            ->where('status', 'active')
            ->whereHas('driverDetail', function ($q) {
                $q->where('is_available', true);
            })
            ->with('driverDetail:car_type')
            ->get()
            ->pluck('driverDetail.car_type')
            ->unique()
            ->filter()
            ->values();

        Log::info('✅ Found ' . $types->count() . ' vehicle types');

        return response()->json([
            'success' => true,
            'data' => $types,
            'count' => $types->count(),
        ]);
    }

    /**
     * Details complets d'un véhicule/chauffeur
     * 
     * GET /api/vehicles/{vehicleId}
     */
    public function show($vehicleId)
    {
        Log::info('📡 VehicleController.show called', ['vehicle_id' => $vehicleId]);

        $driver = User::where('id', $vehicleId)
            ->where('role', 'chauffeur')
            ->with('driverDetail')
            ->first();

        if (!$driver) {
            Log::warning('Vehicle/Driver not found', ['vehicle_id' => $vehicleId]);
            return response()->json(['error' => 'Vehicle not found'], 404);
        }

        $vehicle = [
            'id' => $driver->id,
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'driver_phone' => $driver->phone,
            'driver_email' => $driver->email,
            'profile_picture' => $driver->profile_picture,
            'car_type' => $driver->driverDetail?->car_type ?? 'unknown',
            'car_model' => $driver->driverDetail?->car_model ?? 'Unknown Model',
            'car_color' => $driver->driverDetail?->car_color ?? '#000000',
            'car_plate' => $driver->driverDetail?->car_plate ?? 'XX-000-XX',
            'car_year' => $driver->driverDetail?->car_year,
            'car_seats' => $driver->driverDetail?->car_seats,
            'rating' => (float) ($driver->driverDetail?->rating ?? 0),
            'rating_count' => (int) ($driver->driverDetail?->rating_count ?? 0),
            'total_rides' => (int) ($driver->driverDetail?->completed_rides ?? 0),
            'is_available' => (bool) ($driver->driverDetail?->is_available ?? false),
            'year_of_experience' => (int) ($driver->driverDetail?->year_of_experience ?? 0),
        ];

        Log::info('✅ Returning vehicle details', ['vehicle_id' => $vehicleId]);

        return response()->json($vehicle);
    }
}
