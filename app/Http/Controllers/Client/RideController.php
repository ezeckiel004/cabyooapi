<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class RideController extends Controller
{
    // 🔴 SÉCURITÉ: Initialiser Stripe avec la clé secrète
    public function __construct()
    {
        $stripeKey = config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY');
        
        if (empty($stripeKey)) {
            Log::error('❌ STRIPE_SECRET_KEY not found in config or env');
        } else {
            Log::info('✅ Stripe initialized with key');
        }
        
        Stripe::setApiKey($stripeKey);
    }

    // Créer une nouvelle course (format simplifié depuis l'app mobile)
    // ⚠️ IMPORTANT: Cette méthode crée la course mais le client DOIT payer 10% d'acompte AVANT que la réservation soit validée
    public function storeSimple(Request $request)
    {
        Log::info('🎯 Called storeSimple() method');
        $user = Auth::user();

        $validated = $request->validate([
            'pickup_location' => 'required|string|max:500',
            'destination_location' => 'required|string|max:500',
            'pickup_date' => 'required|date_format:Y-m-d',
            'pickup_time' => 'required|date_format:H:i',
            'vehicle_type' => 'required|string',
            'driver_id' => 'nullable|exists:users,id',
            'passengers_count' => 'required|integer|min:1|max:10',
            'estimated_price' => 'nullable|numeric',
            'payment_status' => 'nullable|string|in:pending,paid,failed', // ⭐ NOUVEAU: Peut indiquer que le paiement est déjà payé
            'stripe_payment_intent_id' => 'nullable|string', // 🔴 SÉCURITÉ: Nécessaire pour vérifier le paiement
            'notes' => 'nullable|string|max:500',
        ]);

        Log::info('📤 Creating ride with data:', $validated);

        // Créer la course avec dépôt obligatoire
        try {
            // 🔴 SÉCURITÉ: Si le frontend dit qu'il a payé, valider avec Stripe!
            if ($validated['payment_status'] === 'paid') {
                // Le frontend DOIT envoyer le payment_intent_id pour vérification
                if (empty($validated['stripe_payment_intent_id'])) {
                    Log::warning('🔴 SÉCURITÉ: payment_status=paid mais pas de payment_intent_id!');
                    return response()->json([
                        'message' => 'Erreur: Payment Intent ID manquant',
                        'error' => 'Impossible de vérifier le paiement sans Payment Intent ID',
                    ], 422);
                }

                // Vérifier chez Stripe que ce Payment Intent existe et a succès
                try {
                    $paymentIntent = PaymentIntent::retrieve($validated['stripe_payment_intent_id']);
                    
                    // ✅ SÉCURITÉ: Vérifier que le statut est 'succeeded'
                    if ($paymentIntent->status !== 'succeeded') {
                        Log::warning('🔴 SÉCURITÉ: Payment Intent non réussi!', [
                            'id' => $validated['stripe_payment_intent_id'],
                            'status' => $paymentIntent->status,
                        ]);
                        return response()->json([
                            'message' => 'Erreur: Le paiement n\'a pas été confirmé',
                            'error' => 'Status Stripe: ' . $paymentIntent->status,
                        ], 422);
                    }

                    Log::info('✅ SÉCURITÉ: Payment Intent validé', [
                        'id' => $validated['stripe_payment_intent_id'],
                        'amount' => $paymentIntent->amount,
                        'status' => $paymentIntent->status,
                    ]);
                } catch (\Stripe\Exception\ApiErrorException $e) {
                    Log::error('🔴 SÉCURITÉ: Erreur validation Payment Intent', ['error' => $e->getMessage()]);
                    return response()->json([
                        'message' => 'Erreur: Impossible de vérifier le paiement',
                        'error' => 'Stripe: ' . $e->getMessage(),
                    ], 422);
                }
            }

            // Géocoder les adresses pour obtenir les coordonnées GPS
            $pickupCoords = $this->geocodeAddress($validated['pickup_location']);
            $destCoords = $this->geocodeAddress($validated['destination_location']);

            // Calculer la distance estimée entre les deux adresses
            $distance = 0;
            if ($pickupCoords && $destCoords) {
                $distance = $this->calculateHaversineDistance(
                    $pickupCoords['lat'],
                    $pickupCoords['lng'],
                    $destCoords['lat'],
                    $destCoords['lng']
                );
            } else {
                $distance = 5.0; // Distance par défaut si géocodage échoue
            }

            // Combiner pickup_date et pickup_time pour scheduled_at
            $scheduledAt = $validated['pickup_date'] . ' ' . $validated['pickup_time'] . ':00';
            
            // 🔴 IMPORTANT: Utiliser UNIQUEMENT le prix fixe fourni par le frontend
            // Ne PAS recalculer avec la distance pour éviter les doublons
            if (!isset($validated['estimated_price']) || is_null($validated['estimated_price'])) {
                // Fallback si aucun prix fourni (ne devrait pas arriver)
                $estimatedPrice = 45.0; // Prix par défaut fixe
                Log::warning('⚠️ Aucun prix fourni par le frontend, utilisation du prix par défaut: 45.0');
            } else {
                $estimatedPrice = (float) $validated['estimated_price'];
                Log::info('💰 Utilisation du prix fixe du frontend: ' . $estimatedPrice);
            }
            
            // 🔴 OBLIGATOIRE: Calculer l'acompte (10%) et le montant restant
            $depositPercentage = 0.10; // 10% obligatoire
            $depositAmount = round($estimatedPrice * $depositPercentage, 2);
            $amountDue = round($estimatedPrice * (1 - $depositPercentage), 2); // 90% restant
            
            // 🔴 IMPORTANT: Déterminer le statut du dépôt basé sur le payment_status du frontend
            // Si le paiement est déjà payé (payment_status = 'paid'), marquer le dépôt comme payé
            $depositStatus = 'pending'; // Par défaut
            $depositPaidAt = null;
            
            if ($validated['payment_status'] === 'paid') {
                $depositStatus = 'paid'; // ✅ Le paiement a été confirmé côté frontend/Stripe
                $depositPaidAt = now();
                Log::info('💳 Dépôt payé, mise à jour du statut à "paid"');
            }
            
            $rideData = [
                'ride_number' => 'RIDE-' . strtoupper(Str::random(8)),
                'client_id' => $user->id,
                'status' => 'pending', // Reste 'pending' jusqu'au paiement de l'acompte
                'pickup_address' => $validated['pickup_location'],
                'dropoff_address' => $validated['destination_location'],
                'pickup_location' => $pickupCoords ? json_encode($pickupCoords) : json_encode(['lat' => 0, 'lng' => 0]),
                'dropoff_location' => $destCoords ? json_encode($destCoords) : json_encode(['lat' => 0, 'lng' => 0]),
                'pickup_date' => $validated['pickup_date'],
                'pickup_time' => $validated['pickup_time'],
                'scheduled_at' => $scheduledAt,
                'vehicle_type' => $validated['vehicle_type'],
                'driver_id' => null, // Pas de chauffeur jusqu'au paiement
                'passengers_count' => $validated['passengers_count'],
                'distance_km' => round($distance, 2),
                'estimated_price' => $estimatedPrice,
                'final_price' => null, // Sera calculé après
                'payment_method' => 'card', // 🔴 OBLIGATOIRE: Stripe uniquement, plus d'espèces
                'payment_status' => 'pending', // 🔴 IMPORTANT: Toujours 'pending' au départ, passera à 'paid' seulement quand le solde (90%) est payé
                'notes' => $validated['notes'] ?? null,
                // 🔴 CHAMPS DÉPÔT: Mis à jour selon le statut du paiement
                'deposit_amount' => $depositAmount,
                'deposit_status' => $depositStatus, // ✅ Peut être 'paid' ou 'pending'
                'stripe_payment_intent_id' => $validated['stripe_payment_intent_id'] ?? null, // ✅ SÉCURITÉ: Tracer le paiement Stripe
                'stripe_payment_method_id' => null,
                'deposit_paid_at' => $depositPaidAt, // ✅ Mis à jour si payé
                'payment_error' => null,
            ];

            $ride = Ride::create($rideData);

            Log::info('✅ Ride créée:', $ride->toArray());

            // 💳 NOUVEAU: Créer une entrée dans la table payments si l'acompte est payé
            if ($depositStatus === 'paid') {
                try {
                    Payment::create([
                        'ride_id' => $ride->id,
                        'user_id' => $user->id,
                        'amount' => $depositAmount,
                        'method' => 'card', // Paiement par carte Stripe
                        'status' => 'completed', // Paiement réussi
                        'transaction_id' => $validated['stripe_payment_intent_id'],
                        'payment_details' => [
                            'type' => 'deposit',
                            'percentage' => '10%',
                            'description' => 'Acompte pour la course RIDE-' . $ride->ride_number,
                            'stripe_payment_intent_id' => $validated['stripe_payment_intent_id'],
                        ],
                        'paid_at' => now(),
                    ]);
                    
                    Log::info('💳 Entrée Payment créée pour l\'acompte', [
                        'ride_id' => $ride->id,
                        'amount' => $depositAmount,
                        'payment_intent_id' => $validated['stripe_payment_intent_id'],
                    ]);
                } catch (\Exception $e) {
                    Log::error('❌ Erreur création entrée Payment:', ['error' => $e->getMessage()]);
                    // Ne pas bloquer la création de la course si échec du paiement
                }
            }
            
            // Déterminer le message de réponse selon le statut du dépôt
            if ($depositStatus === 'paid') {
                $statusMessage = '✅ Paiement d\'acompte confirmé! Votre course est maintenant visible aux chauffeurs.';
                $httpStatus = 201;
            } else {
                $statusMessage = '⚠️ Acompte de ' . $depositAmount . ' XOF doit être payé avant validation';
                $httpStatus = 201;
            }

            return response()->json([
                'message' => 'Course créée avec succès',
                'data' => $ride,
                // 🔴 IMPORTANT: Informations pour le paiement Stripe
                'deposit_required' => $depositStatus === 'pending', // true si paiement en attente
                'deposit_status' => $depositStatus,
                'deposit_amount' => $depositAmount,
                'deposit_currency' => 'XOF',
                'amount_due_later' => $amountDue, // Montant restant à payer après la course
                'total_price' => $estimatedPrice,
                'status_message' => $statusMessage,
            ], $httpStatus);
        } catch (\Exception $e) {
            Log::error('❌ Erreur création course:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la création de la course',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    // Créer une nouvelle course (FORMAT COMPLET avec coordonnées GPS - NON UTILISÉ PAR MOBILE)
    public function storeFullFormat(Request $request)
    {
        Log::info('🎯 Called storeFullFormat() method - full format with GPS coords');
        $user = Auth::user();

        $validated = $request->validate([
            'pickup_location' => 'required|array',
            'pickup_location.lat' => 'required|numeric',
            'pickup_location.lng' => 'required|numeric',
            'dropoff_location' => 'required|array',
            'dropoff_location.lat' => 'required|numeric',
            'dropoff_location.lng' => 'required|numeric',
            'pickup_address' => 'required|string|max:500',
            'dropoff_address' => 'required|string|max:500',
            'distance_km' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,mobile_money,card',
            'scheduled_at' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:500',
        ]);

        // Calculer le prix estimé
        $estimatedPrice = $this->calculatePrice($validated['distance_km']);

        // Vérifier les suppléments
        $isNightSurcharge = $this->isNightTime();
        $isAirportSurcharge = $this->isAirportLocation($validated['pickup_location']);
        $isLongDistance = $validated['distance_km'] > Setting::getValue('long_distance_threshold_km', 20);

        // Appliquer les suppléments
        if ($isNightSurcharge) {
            $nightSurcharge = $estimatedPrice * (Setting::getValue('night_surcharge_percent', 0.20) / 100);
            $estimatedPrice += $nightSurcharge;
        }

        if ($isAirportSurcharge) {
            $estimatedPrice += Setting::getValue('airport_surcharge', 500);
        }

        if ($isLongDistance) {
            $longDistanceSurcharge = $estimatedPrice * (Setting::getValue('long_distance_surcharge_percent', 0.10) / 100);
            $estimatedPrice += $longDistanceSurcharge;
        }

        // Vérifier le prix minimum
        $minPrice = Setting::getValue('min_price', 1000);
        if ($estimatedPrice < $minPrice) {
            $estimatedPrice = $minPrice;
        }

        // Préparer les données pour la création
        $rideData = [
            'ride_number' => 'RIDE-' . strtoupper(Str::random(8)),
            'client_id' => $user->id,
            'status' => 'pending',
            'pickup_location' => $validated['pickup_location'],
            'dropoff_location' => $validated['dropoff_location'],
            'pickup_address' => $validated['pickup_address'],
            'dropoff_address' => $validated['dropoff_address'],
            'distance_km' => $validated['distance_km'],
            'estimated_price' => round($estimatedPrice, 2),
            'payment_method' => $validated['payment_method'],
            'notes' => $validated['notes'] ?? null,
            'is_night_surcharge' => $isNightSurcharge,
            'is_airport_surcharge' => $isAirportSurcharge,
            'is_long_distance' => $isLongDistance,
        ];

        // Ajouter scheduled_at seulement s'il est présent
        if (isset($validated['scheduled_at'])) {
            $rideData['scheduled_at'] = $validated['scheduled_at'];
        }

        // Créer la course
        $ride = Ride::create($rideData);

        // TODO: Notifier les chauffeurs disponibles
        // TODO: Si c'est une course programmée, planifier une notification

        return response()->json([
            'message' => 'Course créée avec succès',
            'ride' => $ride,
            'estimated_price' => $estimatedPrice,
        ], 201);
    }

    // Liste des courses du client
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Ride::where('client_id', $user->id)
            ->with('driver')
            ->with('driver.driverDetail'); // 🔴 AJOUTER le chargement des détails du chauffeur

        // 🔴 RETOURNER TOUTES LES COURSES (paid, pending, ET NULL)
        // Le client doit voir :
        // - Les courses avec acompte payé (deposit_status = 'paid')
        // - Les courses en attente de paiement (deposit_status = 'pending')
        // - Les anciennes courses (deposit_status = NULL) créées avant le système de dépôt
        
        if ($request->has('deposit_status')) {
            $depositStatus = $request->input('deposit_status');
            if ($depositStatus !== 'all') {
                $query->where('deposit_status', $depositStatus);
            }
            // Si 'all', on affiche tout (pas de filtre)
        } else {
            // Par défaut: afficher TOUTES les courses (pending, paid, ET NULL)
            // No filter - retourner tout sans restriction
        }

        // Filtres additionnels
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // ⚠️ RETOURNER TOUTES LES COURSES SANS PAGINATION
        $rides = $query->orderBy('created_at', 'desc')->get();
        
        Log::info('📊 Courses trouvées pour le client: ' . $rides->count());
        
        // Ajouter des infos utiles à chaque course
        $formattedRides = $rides->map(function ($ride) {
            $ride['amount_due_later'] = round($ride->estimated_price * 0.90, 2); // 90% restant
            $ride['deposit_paid'] = ($ride->deposit_status ?? null) === 'paid';
            return $ride;
        })->toArray();

        Log::info('✅ Retour de ' . count($formattedRides) . ' courses formatées');

        return response()->json([
            'message' => 'Courses récupérées avec succès',
            'data' => $formattedRides,
            'total' => count($formattedRides),
        ]);
    }

    // Détails d'une course
    public function show(Ride $ride)
    {
        $user = Auth::user();

        // Vérifier que la course appartient au client
        if ($ride->client_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé à cette course'
            ], 403);
        }

        // Charger le chauffeur avec ses informations de localisation
        $ride->load([
            'driver' => function ($query) {
                $query->with(['driverDetail:id,user_id,current_latitude,current_longitude,last_location_update']);
            },
            'payment'
        ]);
        
        // 🔴 Ajouter infos importantes de paiement
        $rideData = $ride->toArray();
        $rideData['amount_due_later'] = round($ride->estimated_price * 0.90, 2); // 90% restant après acompte
        $rideData['deposit_paid'] = $ride->deposit_status === 'paid';
        $rideData['can_be_seen_by_drivers'] = $ride->deposit_status === 'paid'; // Visible aux chauffeurs seulement si acompte payé
        
        // 🗺️ ROUTE TRACKING: Extraire les coordonnées GPS nécessaires pour l'affichage de la route
        // IMPORTANT: pickup_location et dropoff_location sont des JSON strings, doivent être décodés
        
        // Décoder pickup_location
        $pickupLocation = null;
        if ($ride->pickup_location) {
            if (is_array($ride->pickup_location)) {
                $pickupLocation = $ride->pickup_location;
            } elseif (is_string($ride->pickup_location)) {
                $pickupLocation = json_decode($ride->pickup_location, true);
            }
        }
        
        // Décoder dropoff_location
        $dropoffLocation = null;
        if ($ride->dropoff_location) {
            if (is_array($ride->dropoff_location)) {
                $dropoffLocation = $ride->dropoff_location;
            } elseif (is_string($ride->dropoff_location)) {
                $dropoffLocation = json_decode($ride->dropoff_location, true);
            }
        }
        
        // Extraire pickup_latitude et pickup_longitude
        if ($pickupLocation && is_array($pickupLocation)) {
            $rideData['pickup_latitude'] = $pickupLocation['lat'] ?? null;
            $rideData['pickup_longitude'] = $pickupLocation['lng'] ?? null;
        } else {
            $rideData['pickup_latitude'] = null;
            $rideData['pickup_longitude'] = null;
        }
        
        // Extraire dropoff_latitude et dropoff_longitude
        if ($dropoffLocation && is_array($dropoffLocation)) {
            $rideData['dropoff_latitude'] = $dropoffLocation['lat'] ?? null;
            $rideData['dropoff_longitude'] = $dropoffLocation['lng'] ?? null;
        } else {
            $rideData['dropoff_latitude'] = null;
            $rideData['dropoff_longitude'] = null;
        }
        
        // 🗺️ Générer les points de trajet pour afficher la route sur la carte
        if ($pickupLocation && is_array($pickupLocation) &&
            $dropoffLocation && is_array($dropoffLocation)) {
            Log::info('🗺️ Calling generateRoutePoints with:', [
                'pickup_location' => $pickupLocation,
                'dropoff_location' => $dropoffLocation,
            ]);
            $routeData = $this->generateRoutePoints($pickupLocation, $dropoffLocation, 15);
            $rideData['route_points'] = $routeData['points'] ?? [];
            $rideData['route_distance_km'] = $routeData['distance_km'] ?? 0;
            $rideData['route_distance_meters'] = $routeData['distance_meters'] ?? 0;
            $rideData['route_duration_minutes'] = $routeData['duration_minutes'] ?? 0;
            $rideData['route_duration_seconds'] = $routeData['duration_seconds'] ?? 0;
            
            Log::info('📊 Route data assigned to response:', [
                'route_points_count' => count($rideData['route_points']),
                'route_distance_km' => $rideData['route_distance_km'],
                'route_duration_minutes' => $rideData['route_duration_minutes'],
            ]);
        } else {
            Log::warning('❌ Missing location data for route generation', [
                'pickup_location' => $pickupLocation,
                'dropoff_location' => $dropoffLocation,
                'pickup_is_array' => is_array($pickupLocation),
                'dropoff_is_array' => is_array($dropoffLocation),
            ]);
            $rideData['route_points'] = [];
            $rideData['route_distance_km'] = 0;
            $rideData['route_distance_meters'] = 0;
            $rideData['route_duration_minutes'] = 0;
            $rideData['route_duration_seconds'] = 0;
        }
        
        // 📍 Calculer la distance et temps restants en fonction de la position actuelle du chauffeur
        $remainingDistanceKm = 0;
        $remainingDurationMinutes = 0;
        
        // Si la course est en cours (ongoing) et on a la position du driver
        if ($ride->status === 'ongoing' && 
            $ride->driver && $ride->driver->driverDetail &&
            $ride->driver->driverDetail->current_latitude &&
            $ride->driver->driverDetail->current_longitude &&
            $dropoffLocation && is_array($dropoffLocation)) {
            
            $driverLat = (float) $ride->driver->driverDetail->current_latitude;
            $driverLng = (float) $ride->driver->driverDetail->current_longitude;
            $dropoffLat = (float) $dropoffLocation['lat'];
            $dropoffLng = (float) $dropoffLocation['lng'];
            
            // Calculer la distance restante
            $remainingDistanceKm = $this->calculateDistance($driverLat, $driverLng, $dropoffLat, $dropoffLng);
            
            // Estimer le temps (supposant une vitesse moyenne de 40 km/h)
            $averageSpeedKmh = 40;
            $remainingDurationMinutes = round(($remainingDistanceKm / $averageSpeedKmh) * 60);
            
            Log::info('📍 Live tracking - Remaining distance:', [
                'driver_lat' => $driverLat,
                'driver_lng' => $driverLng,
                'dropoff_lat' => $dropoffLat,
                'dropoff_lng' => $dropoffLng,
                'remaining_distance_km' => $remainingDistanceKm,
                'remaining_duration_minutes' => $remainingDurationMinutes,
            ]);
        }
        
        // Ajouter les distances restantes à la réponse
        $rideData['remaining_distance_km'] = $remainingDistanceKm;
        $rideData['remaining_duration_minutes'] = $remainingDurationMinutes;
        
        return response()->json($rideData);
    }

    // Annuler une course
    public function cancel(Request $request, Ride $ride)
    {
        $user = Auth::user();

        // Vérifications
        if ($ride->client_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé'
            ], 403);
        }

        if (!in_array($ride->status, ['pending', 'assigned'])) {
            return response()->json([
                'message' => 'Cette course ne peut plus être annulée'
            ], 400);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $ride->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => 'client',
            'cancellation_reason' => $request->reason,
        ]);

        // Si la course était assignée, rendre le chauffeur disponible
        if ($ride->driver_id) {
            $ride->driver->driverDetail->update(['is_available' => true]);

            // TODO: Notifier le chauffeur
        }

        // TODO: Notifier l'admin
        // TODO: Appliquer des pénalités si annulation trop fréquente

        return response()->json([
            'message' => 'Course annulée',
            'ride' => $ride
        ]);
    }

    // Suivre une course
    public function track(Ride $ride)
    {
        $user = Auth::user();

        // Vérifier que la course appartient au client
        if ($ride->client_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé à cette course'
            ], 403);
        }

        if (!in_array($ride->status, ['assigned', 'ongoing'])) {
            return response()->json([
                'message' => 'Cette course ne peut pas être suivie'
            ], 400);
        }

        $data = [
            'ride' => $ride->load('driver'),
            'driver_location' => null, // À implémenter avec websocket ou polling
            'estimated_arrival' => null, // À calculer avec la distance et la vitesse
            'status_updates' => [
                'accepted_at' => $ride->accepted_at,
                'started_at' => $ride->started_at,
                'current_status' => $ride->status,
            ],
        ];

        // Si le chauffeur a partagé sa position
        if ($ride->driver && $ride->driver->driverDetail) {
            // TODO: Récupérer la dernière position du chauffeur
        }

        return response()->json($data);
    }

    // Helper: Calculer le prix
    private function calculatePrice($distance)
    {
        $pricePerKm = Setting::getValue('price_per_km', 500);
        return $distance * $pricePerKm;
    }

    // Helper: Vérifier si c'est la nuit
    private function isNightTime()
    {
        $hour = now()->hour;
        return $hour >= 22 || $hour < 6;
    }

    // Helper: Vérifier si c'est un aéroport
    private function isAirportLocation($location)
    {
        // À implémenter: Vérifier si la position est près d'un aéroport
        // Pour l'instant, on retourne false
        return false;
    }

    // Helper: Géocoder une adresse avec Nominatim (OpenStreetMap)
    private function geocodeAddress($address)
    {
        try {
            $addressEncoded = urlencode($address);
            
            // Nominatim API (OpenStreetMap - gratuit, pas de clé requise)
            $url = "https://nominatim.openstreetmap.org/search?q={$addressEncoded}&format=json&limit=1";
            
            // Ajouter un User-Agent comme demandé par Nominatim
            $context = stream_context_create([
                'http' => [
                    'header' => 'User-Agent: Cabyoo-App/1.0'
                ]
            ]);
            
            $response = json_decode(file_get_contents($url, false, $context), true);
            
            if ($response && count($response) > 0) {
                $result = $response[0];
                
                Log::info('🗺️ Nominatim result:', [
                    'address' => $address,
                    'lat' => $result['lat'],
                    'lon' => $result['lon'],
                    'display_name' => $result['display_name'] ?? '',
                ]);
                
                return [
                    'lat' => (float) $result['lat'],
                    'lng' => (float) $result['lon'],
                ];
            }
            
            Log::warning('⚠️ Nominatim: No results for address', ['address' => $address]);
            return null;
            
        } catch (\Exception $e) {
            Log::error('❌ Error geocoding address:', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // Helper: Calculer la distance entre deux points (en km) avec Haversine
    private function calculateHaversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadiusKm = 6371; // Rayon de la Terre en km
        
        // Convertir les degrés en radians
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);
        
        // Formule Haversine
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) * sin($deltaLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadiusKm * $c;
    }

    // 🔴 NOUVEAU: Payer le solde (90% du prix) d'une course
    public function payBalance(Request $request, Ride $ride)
    {
        $user = Auth::user();

        // Vérifier que la course appartient au client
        if ($ride->client_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé à cette course'
            ], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            Log::info('💳 Initiation du paiement du solde', [
                'ride_id' => $ride->id,
                'amount' => $validated['amount'],
            ]);

            // Calculer le montant attendu du solde (90%)
            $expectedBalance = round($ride->estimated_price * 0.90, 2);
            $submittedAmount = round((float)$validated['amount'], 2);

            // Vérifier que le montant correspond au solde attendu (avec tolérance de 0.01€)
            if (abs($expectedBalance - $submittedAmount) > 0.01) {
                Log::warning('⚠️ Montant du solde incorrect', [
                    'expected' => $expectedBalance,
                    'submitted' => $submittedAmount,
                ]);
                return response()->json([
                    'message' => 'Montant incorrect',
                    'expected' => $expectedBalance,
                ], 400);
            }

            // 🔴 INITIALISER STRIPE ET CRÉER LE PAYMENT INTENT
            // Vérifier que la clé Stripe est configurée
            $stripeKey = env('STRIPE_SECRET_KEY');
            
            // Fallback sur config() si env() ne fonctionne pas
            if (empty($stripeKey)) {
                $stripeKey = config('services.stripe.secret');
            }
            
            // Fallback manuel sur le .env
            if (empty($stripeKey)) {
                $stripeKey = 'sk_test_51SCdmlRoDZ2eaebzkd2KtCKniccsJr4uBDh1SzWfNI422NLUmOlwi2q34wq1aKBKPP9RO65phVrCRH8IwCkFYJuA00sbhZsEyV';
                Log::warning('⚠️ Using hardcoded Stripe key (dev only)');
            }
            
            if (empty($stripeKey)) {
                Log::error('❌ STRIPE_SECRET_KEY not configured');
                return response()->json([
                    'message' => 'Stripe is not configured',
                    'error' => 'STRIPE_SECRET_KEY not found',
                ], 422);
            }

            // Initialiser Stripe avec la clé
            \Stripe\Stripe::setApiKey($stripeKey);
            Log::info('✅ Stripe initialized', ['key_exists' => !empty($stripeKey)]);

            // Créer le Payment Intent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => (int)($submittedAmount * 100), // Centimes
                'currency' => 'eur',
                'description' => "Paiement du solde (90%) - Course #{$ride->ride_number}",
                'metadata' => [
                    'ride_id' => $ride->id,
                    'client_id' => $user->id,
                    'type' => 'balance',
                ],
            ]);

            Log::info('✅ Intention de paiement créée avec succès', [
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $submittedAmount,
            ]);

            return response()->json([
                'message' => 'Intention de paiement créée',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $submittedAmount,
                ],
            ], 200);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('❌ Erreur Stripe API lors de la création de l\'intention de paiement du solde:', [
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode(),
            ]);
            return response()->json([
                'message' => 'Erreur lors de la création de l\'intention de paiement',
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ Erreur lors de la création de l\'intention de paiement du solde:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Erreur lors de la création de l\'intention de paiement',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    // 🔴 NOUVEAU: Confirmer le paiement du solde
    public function confirmBalancePayment(Request $request, Ride $ride)
    {
        $user = Auth::user();

        // Vérifier que la course appartient au client
        if ($ride->client_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé à cette course'
            ], 403);
        }

        $validated = $request->validate([
            'payment_intent_id' => 'required|string',
            'balance_amount' => 'required|numeric|min:0.01',
        ]);

        try {
            Log::info('💳 Confirmation du paiement du solde', [
                'ride_id' => $ride->id,
                'payment_intent_id' => $validated['payment_intent_id'],
                'balance_amount' => $validated['balance_amount'],
            ]);

            // Calculer le montant attendu du solde (90%)
            $expectedBalance = round($ride->estimated_price * 0.90, 2);
            $submittedAmount = round((float)$validated['balance_amount'], 2);

            // Vérifier que le montant correspond
            if (abs($expectedBalance - $submittedAmount) > 0.01) {
                Log::warning('⚠️ Montant du solde incorrect', [
                    'expected' => $expectedBalance,
                    'submitted' => $submittedAmount,
                ]);
                return response()->json([
                    'message' => 'Montant incorrect',
                    'expected' => $expectedBalance,
                ], 400);
            }

            // 🔴 CRÉER D'ABORD LE PAYMENT RECORD DANS LA TABLE PAYMENTS
            // Utiliser les colonnes correctes du modèle Payment
            $payment = \App\Models\Payment::create([
                'ride_id' => $ride->id,
                'user_id' => $user->id,
                'amount' => $submittedAmount, // 90% du prix total
                'method' => 'card', // Enum: 'cash', 'mobile_money', 'card'
                'transaction_id' => $validated['payment_intent_id'], // Stripe payment intent ID
                'status' => 'completed', // Enum: 'pending', 'completed', 'failed', 'refunded'
                'payment_details' => [ // payment_details est un JSON
                    'type' => 'balance',
                    'description' => "Paiement du solde (90%) - Course #{$ride->ride_number}",
                    'payment_intent_id' => $validated['payment_intent_id'],
                    'provider' => 'stripe',
                ],
                'paid_at' => now(),
            ]);

            Log::info('✅ Payment record créé', [
                'payment_id' => $payment->id,
                'ride_id' => $ride->id,
                'amount' => $submittedAmount,
            ]);

            // 🔴 ENSUITE: Mettre à jour le statut du paiement du solde sur la course
            $ride->update([
                'final_price' => $ride->estimated_price, // Totalité payée
                'payment_status' => 'paid', // ✅ Paiement complet
                'stripe_payment_intent_id' => $validated['payment_intent_id'],
            ]);

            Log::info('✅ Ride mise à jour', [
                'ride_id' => $ride->id,
                'payment_status' => 'paid',
                'final_price' => $ride->final_price,
            ]);

            // 💰 NOUVEAU: Ajouter les earnings au chauffeur assigné
            if ($ride->driver_id) {
                try {
                    $driver = \App\Models\User::find($ride->driver_id);
                    
                    if ($driver && $driver->driverDetail) {
                        // Récupérer la commission plateforme depuis les settings
                        $platformCommissionPercent = (float) Setting::getValue('platform_commission_percent', 20);
                        $driverCommissionPercent = 100 - $platformCommissionPercent;
                        
                        // Calculer les earnings: (100% - commission_platform) % du prix final
                        $driverEarnings = round($ride->final_price * ($driverCommissionPercent / 100), 2);
                        $platformCommission = round($ride->final_price * ($platformCommissionPercent / 100), 2);
                        
                        // Ajouter au total earnings du chauffeur
                        $driver->driverDetail->update([
                            'total_earnings' => $driver->driverDetail->total_earnings + $driverEarnings,
                        ]);
                        
                        Log::info('💵 Earnings ajoutés au chauffeur', [
                            'driver_id' => $driver->id,
                            'ride_id' => $ride->id,
                            'final_price' => $ride->final_price,
                            'platform_commission_percent' => $platformCommissionPercent,
                            'driver_commission_percent' => $driverCommissionPercent,
                            'driver_earnings' => $driverEarnings,
                            'platform_commission' => $platformCommission,
                            'total_earnings_now' => $driver->driverDetail->total_earnings + $driverEarnings,
                        ]);
                    } else {
                        Log::warning('⚠️ Chauffeur ou driverDetail non trouvé', [
                            'driver_id' => $ride->driver_id,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('❌ Erreur lors de l\'ajout des earnings', [
                        'error' => $e->getMessage(),
                        'driver_id' => $ride->driver_id,
                        'ride_id' => $ride->id,
                    ]);
                    // Ne pas bloquer le paiement si échec des earnings
                }
            } else {
                Log::warning('⚠️ Aucun chauffeur assigné à cette course', [
                    'ride_id' => $ride->id,
                ]);
            }

            return response()->json([
                'message' => 'Paiement du solde confirmé',
                'ride' => $ride,
                'payment' => $payment,
                'payment_status' => 'paid',
                'final_price' => $ride->final_price,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur lors de la confirmation du paiement du solde:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Erreur lors de la confirmation du paiement',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    // 🔴 NOUVEAU: Récupérer le statut du paiement du solde
    public function getBalancePaymentStatus(Ride $ride)
    {
        $user = Auth::user();

        // Vérifier que la course appartient au client
        if ($ride->client_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé à cette course'
            ], 403);
        }

        $balancePaid = $ride->payment_status === 'paid';
        $balanceAmount = $ride->estimated_price * 0.90;

        return response()->json([
            'ride_id' => $ride->id,
            'balance_paid' => $balancePaid,
            'balance_amount' => round($balanceAmount, 2),
            'payment_status' => $ride->payment_status,
            'deposit_status' => $ride->deposit_status,
        ], 200);
    }

    /**
     * 🗺️ Générer les points de trajet entre le départ et l'arrivée
     * Utilise Google Maps Directions API pour obtenir le vrai trajet routier
     */
    private function generateRoutePoints($pickupCoords, $dropoffCoords, $numberOfPoints = 10)
    {
        Log::info('🗺️ generateRoutePoints START (OSRM):', [
            'pickup' => $pickupCoords,
            'dropoff' => $dropoffCoords,
        ]);

        if (!$pickupCoords || !isset($pickupCoords['lat'], $pickupCoords['lng']) ||
            !$dropoffCoords || !isset($dropoffCoords['lat'], $dropoffCoords['lng'])) {
            Log::error('❌ Invalid coordinates');
            return [
                'points' => [],
                'distance_meters' => 0,
                'distance_km' => 0,
                'duration_seconds' => 0,
                'duration_minutes' => 0,
            ];
        }

        $pickupLat = (float) $pickupCoords['lat'];
        $pickupLng = (float) $pickupCoords['lng'];
        $dropoffLat = (float) $dropoffCoords['lat'];
        $dropoffLng = (float) $dropoffCoords['lng'];

        try {
            // OSRM API - GRATUIT! 100% OPEN SOURCE! Pas besoin de clé API
            // Format: /route/v1/driving/lon1,lat1;lon2,lat2?overview=full&geometries=geojson
            $url = "https://router.project-osrm.org/route/v1/driving/{$pickupLng},{$pickupLat};{$dropoffLng},{$dropoffLat}";
            
            Log::info('📡 Calling OSRM API...', [
                'url' => $url,
            ]);

            $client = new \GuzzleHttp\Client();
            $response = $client->get($url, [
                'query' => [
                    'overview' => 'full',
                    'geometries' => 'geojson',  // IMPORTANT: Retourne les vraies coordonnées, pas polyline encodée
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('📈 OSRM Response:', [
                'code' => $data['code'] ?? 'UNKNOWN',
                'has_routes' => isset($data['routes']),
                'routes_count' => isset($data['routes']) ? count($data['routes']) : 0,
            ]);

            if ($data['code'] === 'Ok' && isset($data['routes']) && count($data['routes']) > 0) {
                $route = $data['routes'][0];
                $points = [];

                // OSRM avec geometries=geojson retourne geometry.coordinates [lng, lat]
                if (isset($route['geometry']['coordinates'])) {
                    $coordinates = $route['geometry']['coordinates'];
                    
                    Log::info('✅ Route coordinates extracted:', [
                        'count' => count($coordinates),
                    ]);

                    // Convertir [lng, lat] en [lat, lng] pour Flutter
                    foreach ($coordinates as $coord) {
                        $points[] = [
                            'latitude' => (float) $coord[1],
                            'longitude' => (float) $coord[0],
                        ];
                    }

                    // Récupérer distance et duration depuis OSRM
                    $distanceMeters = $route['distance'] ?? 0;
                    $durationSeconds = $route['duration'] ?? 0;
                    
                    // Convertir en km et minutes
                    $distanceKm = round($distanceMeters / 1000, 2);
                    $durationMinutes = round($durationSeconds / 60);

                    Log::info('✅ RoutePoints created:', [
                        'count' => count($points),
                        'distance_km' => $distanceKm,
                        'duration_minutes' => $durationMinutes,
                    ]);

                    return [
                        'points' => $points,
                        'distance_meters' => $distanceMeters,
                        'distance_km' => $distanceKm,
                        'duration_seconds' => $durationSeconds,
                        'duration_minutes' => $durationMinutes,
                    ];
                }
            }

            Log::warning('⚠️ OSRM no routes, using linear fallback', [
                'code' => $data['code'] ?? 'UNKNOWN',
                'message' => $data['message'] ?? null,
            ]);
            $linearPoints = $this->generateLinearRoutePoints($pickupLat, $pickupLng, $dropoffLat, $dropoffLng, $numberOfPoints);
            return [
                'points' => $linearPoints,
                'distance_meters' => 0,
                'distance_km' => 0,
                'duration_seconds' => 0,
                'duration_minutes' => 0,
            ];

        } catch (\Exception $e) {
            Log::error('❌ OSRM Error:', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            $linearPoints = $this->generateLinearRoutePoints($pickupLat, $pickupLng, $dropoffLat, $dropoffLng, $numberOfPoints);
            return [
                'points' => $linearPoints,
                'distance_meters' => 0,
                'distance_km' => 0,
                'duration_seconds' => 0,
                'duration_minutes' => 0,
            ];
        }
    }

    /**
     * Décode un polyline encodé (Google's polyline encoding)
     * @param string $encoded Polyline encodé
     * @return array Points décodés [[lat, lng], ...]
     */
    private function decodePolyline($encoded)
    {
        $points = [];
        $index = 0;
        $lat = 0;
        $lng = 0;

        while ($index < strlen($encoded)) {
            $result = 0;
            $shift = 0;

            do {
                $char = ord($encoded[$index++]) - 63;
                $result |= ($char & 0x1f) << $shift;
                $shift += 5;
            } while ($char >= 0x20);

            $lat += ($result & 1) ? ~($result >> 1) : $result >> 1;

            $result = 0;
            $shift = 0;

            do {
                $char = ord($encoded[$index++]) - 63;
                $result |= ($char & 0x1f) << $shift;
                $shift += 5;
            } while ($char >= 0x20);

            $lng += ($result & 1) ? ~($result >> 1) : $result >> 1;

            $points[] = [$lat / 1e5, $lng / 1e5];
        }

        return $points;
    }

    /**
     * Fallback: Générer une interpolation linéaire simple
     * Utilisé quand Google Directions API n'est pas disponible
     */
    private function generateLinearRoutePoints($pickupLat, $pickupLng, $dropoffLat, $dropoffLng, $numberOfPoints = 15)
    {
        $points = [];

        // Ajouter le point de départ
        $points[] = [
            'latitude' => $pickupLat,
            'longitude' => $pickupLng,
        ];

        // Générer des points intermédiaires
        for ($i = 1; $i < $numberOfPoints; $i++) {
            $fraction = $i / $numberOfPoints;
            $latDiff = $dropoffLat - $pickupLat;
            $lngDiff = $dropoffLng - $pickupLng;
            
            $points[] = [
                'latitude' => $pickupLat + ($latDiff * $fraction),
                'longitude' => $pickupLng + ($lngDiff * $fraction),
            ];
        }

        // Ajouter le point d'arrivée
        $points[] = [
            'latitude' => $dropoffLat,
            'longitude' => $dropoffLng,
        ];

        return $points;
    }

    /**
     * Calcule la distance entre deux points GPS en km (Haversine formula)
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371;  // Rayon de la Terre en km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * asin(sqrt($a));
        
        return $earthRadius * $c;
    }
}

