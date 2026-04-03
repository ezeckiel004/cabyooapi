<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Ride;

// Récupérer la course 54
$ride = Ride::find(54);

if (!$ride) {
    die("Ride 54 not found\n");
}

echo "=== RIDE 54 DATA ===\n";
echo "Status: " . $ride->status . "\n";
echo "Pickup Address: " . $ride->pickup_address . "\n";
echo "Dropoff Address: " . $ride->dropoff_address . "\n";
echo "Pickup Location Type: " . gettype($ride->pickup_location) . "\n";
echo "Dropoff Location Type: " . gettype($ride->dropoff_location) . "\n";

if (is_array($ride->pickup_location)) {
    echo "Pickup Location Keys: " . implode(', ', array_keys($ride->pickup_location)) . "\n";
    echo "Pickup Location JSON: " . json_encode($ride->pickup_location) . "\n";
}

if (is_array($ride->dropoff_location)) {
    echo "Dropoff Location Keys: " . implode(', ', array_keys($ride->dropoff_location)) . "\n";
    echo "Dropoff Location JSON: " . json_encode($ride->dropoff_location) . "\n";
}

// Test: Vérifier si on peut extraire les coordonnées
echo "\n=== COORDINATE EXTRACTION TEST ===\n";

if ($ride->pickup_location && is_array($ride->pickup_location)) {
    $pickupLat = $ride->pickup_location['lat'] ?? null;
    $pickupLng = $ride->pickup_location['lng'] ?? null;
    echo "✓ Pickup Lat: $pickupLat\n";
    echo "✓ Pickup Lng: $pickupLng\n";
} else {
    echo "✗ Cannot extract pickup coordinates\n";
}

if ($ride->dropoff_location && is_array($ride->dropoff_location)) {
    $dropoffLat = $ride->dropoff_location['lat'] ?? null;
    $dropoffLng = $ride->dropoff_location['lng'] ?? null;
    echo "✓ Dropoff Lat: $dropoffLat\n";
    echo "✓ Dropoff Lng: $dropoffLng\n";
} else {
    echo "✗ Cannot extract dropoff coordinates\n";
}

// Test: Vérifier la conversion en array via toArray()
echo "\n=== RIDE->toArray() TEST ===\n";
$rideData = $ride->toArray();
echo "Keys in rideData: " . count($rideData) . "\n";
echo "Has pickup_location: " . (isset($rideData['pickup_location']) ? 'yes' : 'no') . "\n";
echo "Has dropoff_location: " . (isset($rideData['dropoff_location']) ? 'yes' : 'no') . "\n";

if (isset($rideData['pickup_location'])) {
    echo "pickup_location in rideData: " . json_encode($rideData['pickup_location']) . "\n";
}

if (isset($rideData['dropoff_location'])) {
    echo "dropoff_location in rideData: " . json_encode($rideData['dropoff_location']) . "\n";
}
