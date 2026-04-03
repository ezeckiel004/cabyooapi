<?php

// Teste les vraies routes d'une course avec status='ongoing'

require 'vendor/autoload.php';

echo "🧪 Testing OSRM-backed Route Points\n";
echo "=====================================\n\n";

// URL de l'API
$rideId = 54;  // Un ride avec status='ongoing'
$baseUrl = 'https://api.cabyoo.com';
$url = "$baseUrl/api/client/rides/$rideId";
$token = 'YOUR_TOKEN_HERE';  // Remplace par un vrai token

echo "Fetching ride $rideId from: $url\n\n";

try {
    $client = new \GuzzleHttp\Client();
    $response = $client->get($url, [
        'headers' => [
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json',
        ],
    ]);

    $data = json_decode($response->getBody(), true);

    if (isset($data['route_points'])) {
        $routePoints = $data['route_points'];
        echo "✅ SUCCESS! Route points reçus!\n\n";
        echo "Total route points: " . count($routePoints) . "\n\n";

        if (!empty($routePoints)) {
            echo "First 10 points:\n";
            for ($i = 0; $i < min(10, count($routePoints)); $i++) {
                $point = $routePoints[$i];
                echo "  $i: [{$point['latitude']}, {$point['longitude']}]\n";
            }

            echo "\nLast 5 points:\n";
            $start = max(0, count($routePoints) - 5);
            for ($i = $start; $i < count($routePoints); $i++) {
                $point = $routePoints[$i];
                echo "  $i: [{$point['latitude']}, {$point['longitude']}]\n";
            }

            echo "\n✅ Cette route va s'afficher sur la carte avec TOUTES les courbes!\n";
            echo "\nPickup: [{$data['pickup_latitude']}, {$data['pickup_longitude']}]\n";
            echo "Dropoff: [{$data['dropoff_latitude']}, {$data['dropoff_longitude']}]\n";
        }
    } else {
        echo "⚠️ No route_points in response\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
