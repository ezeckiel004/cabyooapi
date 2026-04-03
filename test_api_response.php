<?php

// Test rapide - Voir ce que l'API renvoie réellement

require 'vendor/autoload.php';

echo "🧪 Testing API Response\n";
echo "=======================\n\n";

$rideId = 54;
$url = "https://api.cabyoo.com/api/client/rides/$rideId";

try {
    $client = new \GuzzleHttp\Client();
    $response = $client->get($url, [
        'headers' => [
            'Authorization' => 'Bearer 123',
            'Accept' => 'application/json',
        ],
    ]);

    $data = json_decode($response->getBody(), true);
    
    // Chercher les clés
    echo "Keys en haut niveau:\n";
    foreach (array_keys($data) as $key) {
        if (strpos($key, 'route') !== false || strpos($key, 'distance') !== false || strpos($key, 'duration') !== false) {
            echo "  ✅ $key: " . json_encode($data[$key]) . "\n";
        }
    }
    
    echo "\nTous les keys:\n";
    print_r(array_keys($data));
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
