<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Ride;
use Illuminate\Support\Facades\Auth;

Auth::loginUsingId(12);

$ride = Ride::find(54);
if (!$ride) {
    die("Ride not found\n");
}

echo "=== CHECKING API RESPONSE DATA ===\n\n";

echo "1. Raw database values:\n";
echo "   - pickup_location: " . json_encode($ride->pickup_location) . "\n";
echo "   - dropoff_location: " . json_encode($ride->dropoff_location) . "\n";

$ride->load(['driver' => function($q) { $q->with('driverDetail:id,user_id,current_latitude,current_longitude,last_location_update'); }, 'payment']);

echo "\n2. After load (checking types):\n";
echo "   - is_array(pickup): " . (is_array($ride->pickup_location) ? 'YES' : 'NO') . "\n";
echo "   - is_array(dropoff): " . (is_array($ride->dropoff_location) ? 'YES' : 'NO') . "\n";

// SIMULATE show() method
$rideData = $ride->toArray();

echo "\n3. In toArray():\n";
echo "   - pickup_location in array: " . (isset($rideData['pickup_location']) ? 'YES' : 'NO') . "\n";
echo "   - dropoff_location in array: " . (isset($rideData['dropoff_location']) ? 'YES' : 'NO') . "\n";

if (isset($rideData['pickup_location'])) {
    echo "   - pickup_location value: " . json_encode($rideData['pickup_location']) . "\n";
    echo "   - pickup_location is_array: " . (is_array($rideData['pickup_location']) ? 'YES' : 'NO') . "\n";
}

// NOW APPLY CONTROLLER LOGIC (UPDATED - WITH JSON DECODE)
echo "\n4. Controller logic - extracting coordinates (WITH JSON DECODE):\n";

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

echo "   - Decoded pickupLocation: " . json_encode($pickupLocation) . "\n";
echo "   - Decoded dropoffLocation: " . json_encode($dropoffLocation) . "\n";

if ($pickupLocation && is_array($pickupLocation)) {
    $rideData['pickup_latitude'] = $pickupLocation['lat'] ?? null;
    $rideData['pickup_longitude'] = $pickupLocation['lng'] ?? null;
    echo "   ✓ Extracted: lat=" . $rideData['pickup_latitude'] . ", lng=" . $rideData['pickup_longitude'] . "\n";
} else {
    echo "   ✗ Failed to extract\n";
    $rideData['pickup_latitude'] = null;
    $rideData['pickup_longitude'] = null;
}

if ($dropoffLocation && is_array($dropoffLocation)) {
    $rideData['dropoff_latitude'] = $dropoffLocation['lat'] ?? null;
    $rideData['dropoff_longitude'] = $dropoffLocation['lng'] ?? null;
    echo "   ✓ Dropoff: lat=" . $rideData['dropoff_latitude'] . ", lng=" . $rideData['dropoff_longitude'] . "\n";
} else {
    echo "   ✗ Failed to extract dropoff\n";
    $rideData['dropoff_latitude'] = null;
    $rideData['dropoff_longitude'] = null;
}

// ROUTE GENERATION
echo "\n5. Route generation check:\n";
echo "   - pickupLocation is array: " . (is_array($pickupLocation) ? 'YES' : 'NO') . "\n";
echo "   - dropoffLocation is array: " . (is_array($dropoffLocation) ? 'YES' : 'NO') . "\n";

if ($pickupLocation && is_array($pickupLocation) &&
    $dropoffLocation && is_array($dropoffLocation)) {
    echo "   ✓ Conditions met!\n";
    
    $points = [];
    
    for ($i = 0; $i <= 3; $i++) {
        $fraction = $i / 3;
        $lat = $pickupLocation['lat'] + (($dropoffLocation['lat'] - $pickupLocation['lat']) * $fraction);
        $lng = $pickupLocation['lng'] + (($dropoffLocation['lng'] - $pickupLocation['lng']) * $fraction);
        $points[] = ['latitude' => (float)$lat, 'longitude' => (float)$lng];
    }
    
    $rideData['route_points'] = $points;
    echo "   ✓ Generated " . count($points) . " points\n";
} else {
    echo "   ✗ Conditions NOT met\n";
    $rideData['route_points'] = [];
}

// DRIVER
echo "\n6. Driver location:\n";
if ($ride->driver && $ride->driver->driverDetail) {
    echo "   ✓ Found driver with location\n";
    if (!isset($rideData['driver'])) {
        $rideData['driver'] = [];
    }
    $rideData['driver']['current_latitude'] = $ride->driver->driverDetail->current_latitude;
    $rideData['driver']['current_longitude'] = $ride->driver->driverDetail->current_longitude;
    echo "   - Driver lat: " . $rideData['driver']['current_latitude'] . "\n";
    echo "   - Driver lng: " . $rideData['driver']['current_longitude'] . "\n";
} else {
    echo "   ✗ No driver or location\n";
}

// FINAL CHECK
echo "\n=== FINAL API RESPONSE WILL HAVE ===\n";
echo "- status: " . $rideData['status'] . "\n";
echo "- pickup_latitude: " . ($rideData['pickup_latitude'] ?? 'null') . "\n";
echo "- pickup_longitude: " . ($rideData['pickup_longitude'] ?? 'null') . "\n";
echo "- dropoff_latitude: " . ($rideData['dropoff_latitude'] ?? 'null') . "\n";
echo "- dropoff_longitude: " . ($rideData['dropoff_longitude'] ?? 'null') . "\n";
echo "- route_points: " . count($rideData['route_points']) . " points\n";
echo "- driver.current_latitude: " . ($rideData['driver']['current_latitude'] ?? 'null') . "\n";
echo "- driver.current_longitude: " . ($rideData['driver']['current_longitude'] ?? 'null') . "\n";

echo "\n✓ API Response ready!\n";
