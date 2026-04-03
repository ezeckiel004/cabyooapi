<?php

// Test OSRM avec des coordonnées d'exemple

$pickupLng = -4.0026;  // Longitude Abidjan
$pickupLat = 5.3600;   // Latitude Abidjan
$dropoffLng = -4.0200;
$dropoffLat = 5.3800;

$url = "https://router.project-osrm.org/route/v1/driving/{$pickupLng},{$pickupLat};{$dropoffLng},{$dropoffLat}";

echo "🧪 Testing OSRM (100% FREE, No API Key Needed!)\n";
echo "==============================================\n\n";
echo "Pickup: $pickupLat, $pickupLng\n";
echo "Dropoff: $dropoffLat, $dropoffLng\n";
echo "\nURL: $url\n\n";

try {
    require 'vendor/autoload.php';
    $client = new \GuzzleHttp\Client();
    $response = $client->get($url, [
        'query' => [
            'overview' => 'full',
            'geometries' => 'geojson',  // IMPORTANT: Vraies coordonnées, pas polyline encodée!
        ],
    ]);

    $data = json_decode($response->getBody(), true);
    $httpCode = $response->getStatusCode();

    echo "HTTP Code: $httpCode\n\n";

    if ($httpCode === 200 && $data['code'] === 'Ok' && isset($data['routes'])) {
        echo "✅ SUCCESS! OSRM est 100% opérationnel!\n";
        echo "Routes found: " . count($data['routes']) . "\n";

        if (isset($data['routes'][0])) {
            $route = $data['routes'][0];
            
            echo "\n📊 Route structure:\n";
            echo json_encode($route, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
            
            if (isset($route['geometry']['coordinates'])) {
                $coords = $route['geometry']['coordinates'];
                echo "Route coordinates: " . count($coords) . " points\n";
                echo "Distance: " . round($route['distance'] / 1000, 2) . " km\n";
                echo "Duration: " . round($route['duration'] / 60, 1) . " minutes\n\n";
                
                echo "First 10 points:\n";
                for ($i = 0; $i < min(10, count($coords)); $i++) {
                    $lng = $coords[$i][0];
                    $lat = $coords[$i][1];
                    echo "  $i: [lat=$lat, lng=$lng]\n";
                }
                
                echo "\nLast 5 points:\n";
                $start = max(0, count($coords) - 5);
                for ($i = $start; $i < count($coords); $i++) {
                    $lng = $coords[$i][0];
                    $lat = $coords[$i][1];
                    echo "  $i: [lat=$lat, lng=$lng]\n";
                }
                
                echo "\n✅ Cette route sera envoyée au Flutter et affichée avec toutes les courbes!\n";
            }
        }
    } else {
        echo "❌ FAILED!\n";
        echo "Code: " . ($data['code'] ?? 'UNKNOWN') . "\n";
        echo "Message: " . ($data['message'] ?? 'No message') . "\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}
