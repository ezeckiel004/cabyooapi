<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Ride;
use Illuminate\Support\Facades\Auth;

// Simuler un user authentifié (le client)
$client = \App\Models\User::where('role', 'client')->first();
if (!$client) {
    die("No client user found\n");
}

// Authentifier comme ce client
Auth::loginUsingId($client->id);

// Récupérer une course du client
$ride = Ride::where('client_id', $client->id)->where('status', 'ongoing')->first();

if (!$ride) {
    echo "No ongoing ride found for client. Trying any ride:\n";
    $ride = Ride::where('client_id', $client->id)->latest()->first();
}

if (!$ride) {
    die("No rides found for this client\n");
}

echo "=== TESTING API RESPONSE FOR RIDE ID: " . $ride->id . " ===\n\n";

// Charger exactement comme le contrôleur le fait
$ride->load([
    'driver' => function ($query) {
        $query->with(['driverDetail:id,user_id,current_latitude,current_longitude,last_location_update']);
    },
    'payment'
]);

// Convertir en array comme le contrôleur
$rideData = $ride->toArray();

echo "BEFORE MODIFICATIONS:\n";
echo "  pickup_location: " . (isset($rideData['pickup_location']) ? json_encode($rideData['pickup_location']) : 'NOT SET') . "\n";
echo "  dropoff_location: " . (isset($rideData['dropoff_location']) ? json_encode($rideData['dropoff_location']) : 'NOT SET') . "\n";
echo "  pickup_latitude: " . (isset($rideData['pickup_latitude']) ? $rideData['pickup_latitude'] : 'NOT SET') . "\n";

// Appliquer la logique du contrôleur
$rideData['amount_due_later'] = round($ride->estimated_price * 0.90, 2);
$rideData['deposit_paid'] = $ride->deposit_status === 'paid';
$rideData['can_be_seen_by_drivers'] = $ride->deposit_status === 'paid';

// COORDONNÉES
echo "\nEXTRACTING COORDINATES:\n";
echo "  ride->pickup_location is array: " . (is_array($ride->pickup_location) ? 'YES' : 'NO') . "\n";
echo "  ride->pickup_location value: " . json_encode($ride->pickup_location) . "\n";

if ($ride->pickup_location && is_array($ride->pickup_location)) {
    $rideData['pickup_latitude'] = $ride->pickup_location['lat'] ?? null;
    $rideData['pickup_longitude'] = $ride->pickup_location['lng'] ?? null;
    echo "  ✓ Extracted pickup coords: lat=" . $rideData['pickup_latitude'] . ", lng=" . $rideData['pickup_longitude'] . "\n";
} else {
    echo "  ✗ Failed to extract pickup coordinates\n";
    $rideData['pickup_latitude'] = null;
    $rideData['pickup_longitude'] = null;
}

if ($ride->dropoff_location && is_array($ride->dropoff_location)) {
    $rideData['dropoff_latitude'] = $ride->dropoff_location['lat'] ?? null;
    $rideData['dropoff_longitude'] = $ride->dropoff_location['lng'] ?? null;
    echo "  ✓ Extracted dropoff coords: lat=" . $rideData['dropoff_latitude'] . ", lng=" . $rideData['dropoff_longitude'] . "\n";
} else {
    echo "  ✗ Failed to extract dropoff coordinates\n";
    $rideData['dropoff_latitude'] = null;
    $rideData['dropoff_longitude'] = null;
}

// ROUTE POINTS
echo "\nGENERATING ROUTE POINTS:\n";
if ($ride->pickup_location && $ride->dropoff_location && 
    is_array($ride->pickup_location) && is_array($ride->dropoff_location)) {
    echo "  Conditions met for route generation\n";
    // Simulate what generateRoutePoints() would do
    $pickup = $ride->pickup_location;
    $dropoff = $ride->dropoff_location;
    echo "  Pickup: " . json_encode($pickup) . "\n";
    echo "  Dropoff: " . json_encode($dropoff) . "\n";
    
    // Try to generate a few points
    $points = [];
    for ($i = 0; $i <= 5; $i++) {
        $fraction = $i / 5;
        $lat = $pickup['lat'] + (($dropoff['lat'] - $pickup['lat']) * $fraction);
        $lng = $pickup['lng'] + (($dropoff['lng'] - $pickup['lng']) * $fraction);
        $points[] = ['latitude' => $lat, 'longitude' => $lng];
    }
    
    $rideData['route_points'] = $points;
    echo "  ✓ Generated " . count($points) . " route points\n";
    echo "  First point: " . json_encode($points[0]) . "\n";
    echo "  Last point: " . json_encode($points[count($points)-1]) . "\n";
} else {
    echo "  ✗ Conditions NOT met for route generation\n";
    echo "    pickup_location is array: " . (is_array($ride->pickup_location) ? 'YES' : 'NO') . "\n";
    echo "    dropoff_location is array: " . (is_array($ride->dropoff_location) ? 'YES' : 'NO') . "\n";
    $rideData['route_points'] = [];
}

// DRIVER LOCATION
echo "\nDRIVER LOCATION:\n";
if ($ride->driver && $ride->driver->driverDetail) {
    echo "  ✓ Driver and driverDetail found\n";
    echo "  Driver current lat: " . $ride->driver->driverDetail->current_latitude . "\n";
    echo "  Driver current lng: " . $ride->driver->driverDetail->current_longitude . "\n";
    
    if (!isset($rideData['driver'])) {
        $rideData['driver'] = [];
    }
    $rideData['driver']['current_latitude'] = $ride->driver->driverDetail->current_latitude;
    $rideData['driver']['current_longitude'] = $ride->driver->driverDetail->current_longitude;
} else {
    echo "  ✗ No driver or driverDetail\n";
}

// FINAL RESPONSE
echo "\n=== FINAL API RESPONSE (KEY FIELDS) ===\n";
echo "status: " . $rideData['status'] . "\n";
echo "pickup_latitude: " . ($rideData['pickup_latitude'] ?? 'null') . "\n";
echo "pickup_longitude: " . ($rideData['pickup_longitude'] ?? 'null') . "\n";
echo "dropoff_latitude: " . ($rideData['dropoff_latitude'] ?? 'null') . "\n";
echo "dropoff_longitude: " . ($rideData['dropoff_longitude'] ?? 'null') . "\n";
echo "route_points count: " . count($rideData['route_points'] ?? []) . "\n";
if (isset($rideData['route_points']) && count($rideData['route_points']) > 0) {
    echo "route_points[0]: " . json_encode($rideData['route_points'][0]) . "\n";
}
echo "driver.current_latitude: " . ($rideData['driver']['current_latitude'] ?? 'null') . "\n";
echo "driver.current_longitude: " . ($rideData['driver']['current_longitude'] ?? 'null') . "\n";

echo "\n=== FULL JSON RESPONSE ===\n";
echo json_encode($rideData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
