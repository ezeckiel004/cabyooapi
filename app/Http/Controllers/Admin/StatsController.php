<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    // Vue d'ensemble - VISUALISATION SEULEMENT
    public function overview(Request $request)
    {
        // Utilisateurs
        $totalUsers = User::count();
        $totalClients = User::clients()->count();
        $totalDrivers = User::drivers()->count();
        $activeDrivers = User::drivers()->whereHas('driverDetail', function ($q) {
            $q->where('is_available', true);
        })->count();

        // Courses
        $totalRides = Ride::count();
        $completedRides = Ride::completed()->count();
        $cancelledRides = Ride::cancelled()->count();
        $pendingRides = Ride::pending()->count();

        // Revenus - LES DEUX CHAMPS
        $totalEstimatedRevenue = Ride::sum('estimated_price');
        $totalCompletedRevenue = Ride::completed()->sum('final_price');
        $platformRevenue = Ride::completed()->sum('platform_commission');
        $driversRevenue = Ride::completed()->sum('driver_earnings');

        // Statistiques temporelles (7 derniers jours) - pour graphiques
        $last7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $ridesCount = Ride::whereDate('created_at', $date)->count();
            $estimatedRevenue = Ride::whereDate('created_at', $date)->sum('estimated_price');
            $completedRevenue = Ride::whereDate('created_at', $date)
                ->where('status', 'completed')
                ->sum('final_price');

            $last7Days[] = [
                'date' => $date,
                'rides' => $ridesCount,
                'estimated_revenue' => (float) $estimatedRevenue,
                'completed_revenue' => (float) $completedRevenue,
            ];
        }

        // Top chauffeurs - VISUALISATION SEULEMENT
        $topDrivers = User::drivers()
            ->with('driverDetail')
            ->withSum(['driverRides as total_earnings' => function ($query) {
                $query->where('status', 'completed');
            }], 'driver_earnings')
            ->withSum(['driverRides as total_estimated' => function ($query) {
                $query->where('status', '!=', 'cancelled');
            }], 'estimated_price')
            ->withSum(['driverRides as total_completed' => function ($query) {
                $query->where('status', 'completed');
            }], 'final_price')
            ->withCount(['driverRides as completed_rides' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->orderBy('total_earnings', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($driver) {
                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'email' => $driver->email,
                    'phone' => $driver->phone,
                    'total_earnings' => $driver->total_earnings ?? 0,
                    'total_estimated' => $driver->total_estimated ?? 0,
                    'total_completed' => $driver->total_completed ?? 0,
                    'completed_rides' => $driver->completed_rides ?? 0,
                    'car_model' => $driver->driverDetail->car_model ?? null,
                    'car_plate' => $driver->driverDetail->car_plate ?? null,
                    'rating' => $driver->driverDetail->rating ?? 0,
                ];
            });

        // Top clients - VISUALISATION SEULEMENT
        $topClients = User::clients()
            ->withSum(['clientRides as total_spent' => function ($query) {
                $query->where('status', 'completed');
            }], 'final_price')
            ->withSum(['clientRides as total_estimated' => function ($query) {
                $query->where('status', '!=', 'cancelled');
            }], 'estimated_price')
            ->withCount(['clientRides as total_rides' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->orderBy('total_spent', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'total_spent' => $client->total_spent ?? 0,
                    'total_estimated' => $client->total_estimated ?? 0,
                    'total_rides' => $client->total_rides ?? 0,
                ];
            });

        // Aujourd'hui
        $today = now()->format('Y-m-d');
        $todayRides = Ride::whereDate('created_at', $today)->count();
        $todayCompleted = Ride::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->count();
        $todayCancelled = Ride::whereDate('created_at', $today)
            ->where('status', 'cancelled')
            ->count();
        $todayEstimatedRevenue = Ride::whereDate('created_at', $today)->sum('estimated_price');
        $todayCompletedRevenue = Ride::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('final_price');

        return response()->json([
            'users' => [
                'total' => $totalUsers,
                'clients' => $totalClients,
                'drivers' => $totalDrivers,
                'active_drivers' => $activeDrivers,
            ],
            'rides' => [
                'total' => $totalRides,
                'completed' => $completedRides,
                'cancelled' => $cancelledRides,
                'pending' => $pendingRides,
                'today' => $todayRides,
                'today_completed' => $todayCompleted,
                'today_cancelled' => $todayCancelled,
                'completion_rate' => $totalRides > 0 ? round(($completedRides / $totalRides) * 100, 2) : 0,
                'cancellation_rate' => $totalRides > 0 ? round(($cancelledRides / $totalRides) * 100, 2) : 0,
            ],
            'revenue' => [
                'estimated_total' => (float) $totalEstimatedRevenue,
                'completed_total' => (float) $totalCompletedRevenue,
                'platform' => (float) $platformRevenue,
                'drivers' => (float) $driversRevenue,
                'today_estimated' => (float) $todayEstimatedRevenue,
                'today_completed' => (float) $todayCompletedRevenue,
                'average_per_ride_estimated' => $totalRides > 0 ? round($totalEstimatedRevenue / $totalRides, 2) : 0,
                'average_per_ride_completed' => $completedRides > 0 ? round($totalCompletedRevenue / $completedRides, 2) : 0,
            ],
            'last_7_days' => $last7Days,
            'top_drivers' => $topDrivers,
            'top_clients' => $topClients,
            'trend_data' => $this->generateTrendData(),
        ]);
    }

    // Statistiques de revenus - VISUALISATION SEULEMENT
    public function revenue(Request $request)
    {
        $period = $request->get('period', 'month'); // day, week, month, year

        $query = Ride::query();
        $completedQuery = Ride::where('status', 'completed');

        switch ($period) {
            case 'day':
                $query->whereDate('created_at', today());
                $completedQuery->whereDate('created_at', today());
                $groupBy = 'hour';
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                $completedQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                $groupBy = 'day';
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month);
                $completedQuery->whereMonth('created_at', now()->month);
                $groupBy = 'day';
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                $completedQuery->whereYear('created_at', now()->year);
                $groupBy = 'month';
                break;
            default:
                $groupBy = 'day';
        }

        // Revenus totaux - LES DEUX TYPES
        $estimatedRevenue = $query->sum('estimated_price');
        $completedRevenue = $completedQuery->sum('final_price');
        $platformRevenue = $completedQuery->sum('platform_commission');
        $driversRevenue = $completedQuery->sum('driver_earnings');

        // Répartition par méthode de paiement (pour les courses terminées)
        $paymentMethods = $completedQuery->clone()
            ->select('payment_method', DB::raw('SUM(final_price) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->payment_method,
                    'value' => (float) $item->total,
                    'count' => $item->count,
                ];
            });

        // Évolution temporelle - LES DEUX TYPES
        $timeline = $this->generateTimelineData($period, $groupBy, $query, $completedQuery);

        // Top 10 courses par montant - VISUALISATION SEULEMENT
        $topRides = Ride::where('status', 'completed')
            ->with(['client', 'driver'])
            ->orderBy('final_price', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($ride) {
                return [
                    'id' => $ride->id,
                    'ride_number' => $ride->ride_number,
                    'estimated_price' => $ride->estimated_price,
                    'final_price' => $ride->final_price,
                    'client_name' => $ride->client->name ?? 'N/A',
                    'driver_name' => $ride->driver->name ?? 'N/A',
                    'created_at' => $ride->created_at,
                    'pickup_address' => $ride->pickup_address,
                ];
            });

        return response()->json([
            'period' => $period,
            'estimated' => (float) $estimatedRevenue,
            'completed' => (float) $completedRevenue,
            'platform' => (float) $platformRevenue,
            'drivers' => (float) $driversRevenue,
            'payment_methods' => $paymentMethods,
            'timeline' => $timeline,
            'top_rides' => $topRides,
        ]);
    }

    // Statistiques des courses - VISUALISATION SEULEMENT
    public function rides(Request $request)
    {
        $query = Ride::query();

        // Filtres
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Répartition par statut
        $statusDistribution = $query->clone()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->status,
                    'value' => $item->count,
                ];
            });

        // Répartition par heure de la journée
        $hourlyDistribution = [];
        for ($i = 0; $i < 24; $i++) {
            $count = $query->clone()
                ->whereRaw('HOUR(created_at) = ?', [$i])
                ->count();

            $hourlyDistribution[] = [
                'hour' => $i,
                'label' => $i . ':00',
                'count' => $count,
            ];
        }

        // Durée moyenne des courses
        $averageDuration = Ride::where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as avg_minutes'))
            ->first()
            ->avg_minutes ?? 0;

        // Distance moyenne
        $averageDistance = Ride::where('status', 'completed')
            ->avg('distance_km') ?? 0;

        return response()->json([
            'status_distribution' => $statusDistribution,
            'hourly_distribution' => $hourlyDistribution,
            'average_duration_minutes' => round($averageDuration, 2),
            'average_distance_km' => round($averageDistance, 2),
            'total_rides' => $query->count(),
        ]);
    }

    // Statistiques des chauffeurs - VISUALISATION SEULEMENT
    public function drivers(Request $request)
    {
        $query = User::drivers()->with('driverDetail');

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('available')) {
            $query->whereHas('driverDetail', function ($q) use ($request) {
                $q->where('is_available', $request->available == 'true');
            });
        }

        // Statistiques globales
        $totalDrivers = $query->count();
        $activeDrivers = $query->clone()->where('status', 'active')->count();
        $availableDrivers = $query->clone()->whereHas('driverDetail', function ($q) {
            $q->where('is_available', true);
        })->count();

        // Chauffeurs les plus actifs - VISUALISATION SEULEMENT
        $mostActiveDrivers = $query->clone()
            ->withCount(['driverRides as completed_rides' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->withSum(['driverRides as total_earnings' => function ($q) {
                $q->where('status', 'completed');
            }], 'driver_earnings')
            ->withSum(['driverRides as total_estimated' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }], 'estimated_price')
            ->withSum(['driverRides as total_completed' => function ($q) {
                $q->where('status', 'completed');
            }], 'final_price')
            ->orderBy('completed_rides', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($driver) {
                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'email' => $driver->email,
                    'phone' => $driver->phone,
                    'completed_rides' => $driver->completed_rides ?? 0,
                    'total_earnings' => $driver->total_earnings ?? 0,
                    'total_estimated' => $driver->total_estimated ?? 0,
                    'total_completed' => $driver->total_completed ?? 0,
                    'car_model' => $driver->driverDetail->car_model ?? null,
                    'car_plate' => $driver->driverDetail->car_plate ?? null,
                    'rating' => $driver->driverDetail->rating ?? 0,
                    'is_available' => $driver->driverDetail->is_available ?? false,
                ];
            });

        // Meilleures évaluations - VISUALISATION SEULEMENT
        $topRatedDrivers = $query->clone()
            ->whereHas('driverDetail', function ($q) {
                $q->where('rating', '>', 0);
            })
            ->with('driverDetail')
            ->orderByRaw('(SELECT rating FROM driver_details WHERE user_id = users.id) DESC')
            ->limit(10)
            ->get()
            ->map(function ($driver) {
                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'rating' => $driver->driverDetail->rating ?? 0,
                    'completed_rides' => $driver->driverDetail->completed_rides ?? 0,
                    'car_model' => $driver->driverDetail->car_model ?? null,
                ];
            });

        // Performances moyennes
        $averageStats = DB::table('driver_details')
            ->select(
                DB::raw('AVG(rating) as avg_rating'),
                DB::raw('AVG(total_earnings) as avg_earnings'),
                DB::raw('AVG(completed_rides) as avg_completed_rides'),
                DB::raw('AVG(year_of_experience) as avg_experience')
            )
            ->first();

        return response()->json([
            'total_drivers' => $totalDrivers,
            'active_drivers' => $activeDrivers,
            'available_drivers' => $availableDrivers,
            'most_active_drivers' => $mostActiveDrivers,
            'top_rated_drivers' => $topRatedDrivers,
            'average_stats' => $averageStats,
        ]);
    }

    // Méthodes privées auxiliaires
    private function generateTrendData()
    {
        $trendData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $trendData[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'rides' => Ride::whereDate('created_at', $date)->count(),
                'estimated_revenue' => (float) Ride::whereDate('created_at', $date)->sum('estimated_price'),
                'completed_revenue' => (float) Ride::whereDate('created_at', $date)
                    ->where('status', 'completed')
                    ->sum('final_price'),
            ];
        }
        return $trendData;
    }

    private function generateTimelineData($period, $groupBy, $query, $completedQuery)
    {
        $timeline = [];

        if ($groupBy === 'hour') {
            for ($i = 0; $i < 24; $i++) {
                $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
                $estimated = $query->clone()
                    ->whereRaw('HOUR(created_at) = ?', [$i])
                    ->sum('estimated_price');
                $completed = $completedQuery->clone()
                    ->whereRaw('HOUR(created_at) = ?', [$i])
                    ->sum('final_price');

                $timeline[] = [
                    'label' => $hour . ':00',
                    'estimated' => (float) $estimated,
                    'completed' => (float) $completed,
                    'rides' => $query->clone()
                        ->whereRaw('HOUR(created_at) = ?', [$i])
                        ->count(),
                ];
            }
        } elseif ($groupBy === 'day') {
            $days = $period === 'week' ? 7 : date('t');
            for ($i = 1; $i <= $days; $i++) {
                $estimated = $query->clone()
                    ->whereDay('created_at', $i)
                    ->sum('estimated_price');
                $completed = $completedQuery->clone()
                    ->whereDay('created_at', $i)
                    ->sum('final_price');

                $timeline[] = [
                    'label' => "Jour $i",
                    'estimated' => (float) $estimated,
                    'completed' => (float) $completed,
                    'rides' => $query->clone()
                        ->whereDay('created_at', $i)
                        ->count(),
                ];
            }
        } elseif ($groupBy === 'month') {
            for ($i = 1; $i <= 12; $i++) {
                $estimated = $query->clone()
                    ->whereMonth('created_at', $i)
                    ->sum('estimated_price');
                $completed = $completedQuery->clone()
                    ->whereMonth('created_at', $i)
                    ->sum('final_price');

                $timeline[] = [
                    'label' => date('F', mktime(0, 0, 0, $i, 1)),
                    'estimated' => (float) $estimated,
                    'completed' => (float) $completed,
                    'rides' => $query->clone()
                        ->whereMonth('created_at', $i)
                        ->count(),
                ];
            }
        }

        return $timeline;
    }
}
