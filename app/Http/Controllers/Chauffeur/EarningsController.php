<?php

namespace App\Http\Controllers\Chauffeur;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    public function earnings(Request $request)
    {
        try {
            $period = $request->query('period', 'week'); // today, week, month
            $user = auth()->user();
            
            if (!$user || $user->role !== 'chauffeur') {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Déterminer la plage de dates selon la période
            $now = Carbon::now();
            $startDate = match($period) {
                'today' => $now->copy()->startOfDay(),
                'week' => $now->copy()->startOfWeek(),
                'month' => $now->copy()->startOfMonth(),
                'all' => Carbon::createFromDate(2000, 1, 1), // Depuis le début du temps
                default => $now->copy()->subWeek(),
            };

            // 🔴 IMPORTANT: Récupérer TOUTES les courses du chauffeur (acceptées ou complétées)
            // Pour inclure les acomptes (deposits) + gains des courses complétées
            $query = Ride::where('driver_id', $user->id)
                ->whereIn('status', ['assigned', 'ongoing', 'completed']);
            
            // Appliquer le filtre de date sauf pour 'all'
            if ($period !== 'all') {
                $query->whereBetween('created_at', [$startDate, $now]);
            } else {
                $query->where('created_at', '>=', $startDate);
            }
            
            $allRides = $query
                ->with(['client'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculer les stats
            // = acomptes (10%) + gains finaux des courses complétées
            $totalEarnings = $allRides->sum(function ($ride) {
                $earnings = 0;
                
                // Ajouter l'acompte (10%) si payé
                if ($ride->deposit_status === 'paid' && $ride->deposit_amount) {
                    $earnings += (float) $ride->deposit_amount;
                }
                
                // Ajouter le gain final si la course est complétée
                if ($ride->status === 'completed') {
                    $earnings += (float) ($ride->final_price ?? $ride->estimated_price ?? 0);
                }
                
                return $earnings;
            });

            $totalRides = $allRides->count();
            
            // Heures actif (en heures) - seulement les courses complétées
            $completedOnly = $allRides->filter(fn($r) => $r->status === 'completed');
            $activeHours = $completedOnly->sum(function ($ride) {
                if ($ride->started_at && $ride->completed_at) {
                    return $ride->completed_at->diffInMinutes($ride->started_at) / 60;
                }
                return 0;
            });

            $averagePerRide = $totalRides > 0 ? $totalEarnings / $totalRides : 0;

            // Gains quotidiens (acomptes + finaux)
            $dailyEarnings = $allRides->groupBy(function ($ride) {
                return $ride->created_at->format('Y-m-d');
            })->map(function ($rides, $date) {
                return [
                    'day' => Carbon::parse($date)->format('d/m'),
                    'date' => $date,
                    'amount' => $rides->sum(function ($ride) {
                        $earnings = 0;
                        // Acompte si payé
                        if ($ride->deposit_status === 'paid' && $ride->deposit_amount) {
                            $earnings += (float) $ride->deposit_amount;
                        }
                        // Gain final si complété
                        if ($ride->status === 'completed') {
                            $earnings += (float) ($ride->final_price ?? $ride->estimated_price ?? 0);
                        }
                        return $earnings;
                    }),
                ];
            })->values()->toArray();

            // Répartition par type de course (inclut acomptes + gains finaux)
            $earningsByType = [
                [
                    'type' => 'Courts trajets',
                    'amount' => $allRides
                        ->filter(fn($ride) => (float)$ride->distance_km < 10)
                        ->sum(function($ride) {
                            $earnings = 0;
                            if ($ride->deposit_status === 'paid' && $ride->deposit_amount) {
                                $earnings += (float) $ride->deposit_amount;
                            }
                            if ($ride->status === 'completed') {
                                $earnings += (float) ($ride->final_price ?? $ride->estimated_price ?? 0);
                            }
                            return $earnings;
                        }),
                    'percentage' => 0,
                ],
                [
                    'type' => 'Trajets moyens',
                    'amount' => $allRides
                        ->filter(fn($ride) => (float)$ride->distance_km >= 10 && (float)$ride->distance_km < 30)
                        ->sum(function($ride) {
                            $earnings = 0;
                            if ($ride->deposit_status === 'paid' && $ride->deposit_amount) {
                                $earnings += (float) $ride->deposit_amount;
                            }
                            if ($ride->status === 'completed') {
                                $earnings += (float) ($ride->final_price ?? $ride->estimated_price ?? 0);
                            }
                            return $earnings;
                        }),
                    'percentage' => 0,
                ],
                [
                    'type' => 'Longs trajets',
                    'amount' => $allRides
                        ->filter(fn($ride) => (float)$ride->distance_km >= 30)
                        ->sum(function($ride) {
                            $earnings = 0;
                            if ($ride->deposit_status === 'paid' && $ride->deposit_amount) {
                                $earnings += (float) $ride->deposit_amount;
                            }
                            if ($ride->status === 'completed') {
                                $earnings += (float) ($ride->final_price ?? $ride->estimated_price ?? 0);
                            }
                            return $earnings;
                        }),
                    'percentage' => 0,
                ],
            ];

            // Calculer les pourcentages
            $totalByType = array_sum(array_column($earningsByType, 'amount'));
            if ($totalByType > 0) {
                $earningsByType = array_map(function ($type) use ($totalByType) {
                    $type['percentage'] = round(($type['amount'] / $totalByType) * 100, 1);
                    return $type;
                }, $earningsByType);
            }

            // Transactions (courses) - Inclut acomptes et gains finaux
            $transactions = $allRides->map(function ($ride) {
                $amount = 0;
                $txStatus = 'pending';
                
                // Acompte (10%) payé
                if ($ride->deposit_status === 'paid' && $ride->deposit_amount) {
                    $amount = (float) $ride->deposit_amount;
                    $txStatus = 'deposit_paid';
                }
                
                // Gain final si complété
                if ($ride->status === 'completed') {
                    $finalPrice = (float) ($ride->final_price ?? $ride->estimated_price ?? 0);
                    if ($amount > 0) {
                        // Ajouter le solde restant (90%)
                        $amount += $finalPrice - (float) $ride->deposit_amount;
                    } else {
                        $amount = $finalPrice;
                    }
                    $txStatus = 'paid';
                }
                
                return [
                    'id' => $ride->id,
                    'ride' => [
                        'id' => $ride->id,
                        'distance_km' => $ride->distance_km,
                        'duration' => $ride->duration ?? 'N/A',
                        'final_price' => $ride->final_price ?? $ride->estimated_price,
                        'status' => $ride->status,
                        'created_at' => $ride->created_at,
                        'client' => [
                            'id' => $ride->client_id,
                            'name' => $ride->client?->name ?? 'Client',
                        ],
                    ],
                    'amount' => $amount,
                    'status' => $txStatus,
                    'created_at' => $ride->created_at,
                ];
            })->toArray();

            return response()->json([
                'stats' => [
                    'total_earnings' => round($totalEarnings, 2),
                    'total_rides' => $totalRides,
                    'active_hours' => round($activeHours, 1),
                    'average_per_ride' => round($averagePerRide, 2),
                ],
                'transactions' => $transactions,
                'daily_earnings' => $dailyEarnings,
                'earnings_by_type' => $earningsByType,
                'period' => $period,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur serveur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
