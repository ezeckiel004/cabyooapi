<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Ride;
use Illuminate\Support\Facades\DB;

// Créer quelques rides en attente (pending)
echo "Creating pending rides...\n";

$locations = [
    ['address' => 'Gare de Lyon, 75012 Paris', 'lat' => 48.8438, 'lng' => 2.3736],
    ['address' => '104 Avenue des Champs-Élysées, 75008 Paris', 'lat' => 48.8697, 'lng' => 2.3076],
    ['address' => 'Tour Eiffel, 5 Avenue Anatole France, 75007 Paris', 'lat' => 48.8584, 'lng' => 2.2945],
];

for ($i = 0; $i < 3; $i++) {
    $pickup = $locations[$i];
    $dropoff = $locations[($i + 1) % 3];
    
    $ride = Ride::create([
        'ride_number' => 'RIDE-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
        'client_id' => 12,
        'driver_id' => null,
        'status' => 'pending',
        'pickup_location' => json_encode(['latitude' => $pickup['lat'], 'longitude' => $pickup['lng']]),
        'dropoff_location' => json_encode(['latitude' => $dropoff['lat'], 'longitude' => $dropoff['lng']]),
        'pickup_address' => $pickup['address'],
        'dropoff_address' => $dropoff['address'],
        'estimated_price' => 45.00 + ($i * 15),
        'payment_method' => 'card',
        'distance_km' => 8 + ($i * 3),
        'payment_status' => 'pending',
    ]);
    echo "✅ Created pending ride #{$ride->id}\n";
}

// Voir les résumés
$pending = Ride::where('status', 'pending')->count();
$assigned = Ride::where('status', 'assigned')->count();
$ongoing = Ride::where('status', 'ongoing')->count();
$completed = Ride::where('status', 'completed')->count();

echo "\n=== Résumé des statuts ===\n";
echo "Pending: $pending\n";
echo "Assigned: $assigned\n";
echo "Ongoing: $ongoing\n";
echo "Completed: $completed\n";
?>
