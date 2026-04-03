<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AvailabilityController extends Controller
{
    // Récupérer les chauffeurs disponibles avec leurs détails
    public function getAvailable(Request $request)
    {
        Log::info('📡 AvailabilityController.getAvailable called', [
            'car_type' => $request->car_type,
            'query_params' => $request->query(),
        ]);

        $query = User::where('role', 'chauffeur')
            ->where('status', 'active')
            ->with('driverDetail');

        // Retourner TOUS les chauffeurs disponibles, peu importe le car_type
        Log::info('Getting all available drivers (ignoring car_type filter)');
        $query->whereHas('driverDetail', function ($q) {
            $q->where('is_available', true);
        });

        $drivers = $query->get();
        Log::info('✅ Found ' . $drivers->count() . ' drivers');

        // Formater les données
        $formattedDrivers = $drivers->map(function ($driver) {
            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'role' => $driver->role,
                'status' => $driver->status,
                'profile_picture' => $driver->profile_picture,
                'car_model' => $driver->car_model,
                'car_plate' => $driver->car_plate,
                'car_color' => $driver->car_color,
                'year_of_experience' => $driver->year_of_experience,
                'driver_detail' => $driver->driverDetail ? [
                    'id' => $driver->driverDetail->id,
                    'user_id' => $driver->driverDetail->user_id,
                    'driver_license' => $driver->driverDetail->driver_license,
                    'car_model' => $driver->driverDetail->car_model,
                    'car_plate' => $driver->driverDetail->car_plate,
                    'car_color' => $driver->driverDetail->car_color,
                    'car_year' => $driver->driverDetail->car_year,
                    'car_registration' => $driver->driverDetail->car_registration,
                    'car_seats' => $driver->driverDetail->car_seats,
                    'car_type' => $driver->driverDetail->car_type,
                    'car_insurance' => $driver->driverDetail->car_insurance,
                    'car_insurance_expiry' => $driver->driverDetail->car_insurance_expiry,
                    'car_inspection_expiry' => $driver->driverDetail->car_inspection_expiry,
                    'driver_license_expiry' => $driver->driverDetail->driver_license_expiry,
                    'year_of_experience' => $driver->driverDetail->year_of_experience,
                    'total_earnings' => (float) $driver->driverDetail->total_earnings,
                    'completed_rides' => $driver->driverDetail->completed_rides,
                    'rating' => (float) $driver->driverDetail->rating,
                    'rating_count' => $driver->driverDetail->rating_count,
                    'is_available' => $driver->driverDetail->is_available,
                    'base_price' => (float) ($driver->driverDetail->base_price ?? 0),
                    'price_per_km' => (float) ($driver->driverDetail->price_per_km ?? 0),
                ] : null,
            ];
        });

        Log::info('✅ Returning ' . $formattedDrivers->count() . ' formatted drivers');

        return response()->json([
            'drivers' => $formattedDrivers,
            'count' => $formattedDrivers->count(),
        ]);
    }
}
