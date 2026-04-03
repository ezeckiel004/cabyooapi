<?php

// Test direct de l'API Google Directions
require 'vendor/autoload.php';

$apiKey = 'AIzaSyDDOcxKOf9_5hHNVkhRU7OGNYB6kVF6KYA';

// Coordonnées de test pour une vraie course (Adjeoda → Cocody)
$pickupLat = 5.323556;
$pickupLng = -3.901384;
$dropoffLat = 5.547854;
$dropoffLng = -3.978254;

$url = "https://maps.googleapis.com/maps/api/directions/json";
$params = [
    'origin' => "$pickupLat,$pickupLng",
    'destination' => "$dropoffLat,$dropoffLng",
    'key' => $apiKey,
    'mode' => 'driving',
];

echo "🧪 Testing Google Directions API\n";
echo "================================\n";
echo "Origin: $pickupLat, $pickupLng\n";
echo "Destination: $dropoffLat, $dropoffLng\n";
echo "\n";

try {
    $client = new \GuzzleHttp\Client();
    $response = $client->get($url, ['query' => $params]);
    $data = json_decode($response->getBody(), true);

    echo "Status: " . ($data['status'] ?? 'UNKNOWN') . "\n";
    echo "Error: " . ($data['error_message'] ?? 'NONE') . "\n\n";

    if ($data['status'] === 'OK' && isset($data['routes'][0])) {
        $route = $data['routes'][0];
        
        echo "✅ Route found!\n";
        echo "Distance: " . ($route['legs'][0]['distance']['text'] ?? 'N/A') . "\n";
        echo "Duration: " . ($route['legs'][0]['duration']['text'] ?? 'N/A') . "\n\n";

        // Check overview_polyline
        if (isset($route['overview_polyline']['points'])) {
            echo "✅ overview_polyline found: " . strlen($route['overview_polyline']['points']) . " chars\n\n";
        } else {
            echo "❌ NO overview_polyline\n\n";
        }

        // Check steps
        $leg = $route['legs'][0];
        echo "Steps: " . (isset($leg['steps']) ? count($leg['steps']) : 0) . "\n";
        
        if (isset($leg['steps'])) {
            foreach ($leg['steps'] as $i => $step) {
                echo "\nStep $i:\n";
                echo "  Instruction: " . (isset($step['instruction']) ? substr($step['instruction'], 0, 50) : 'N/A') . "\n";
                echo "  Has polyline: " . (isset($step['polyline']['points']) ? 'YES' : 'NO') . "\n";
                if (isset($step['polyline']['points'])) {
                    echo "  Polyline length: " . strlen($step['polyline']['points']) . " chars\n";
                }
            }
        }

        // Full response for inspection
        echo "\n\n📋 Full Response (first 2000 chars):\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        echo "\n❌ API call failed\n";
        echo "Full response:\n";
        var_dump($data);
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Class: " . get_class($e) . "\n";
}
