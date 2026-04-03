<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->string('ride_number')->unique();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'assigned', 'ongoing', 'completed', 'cancelled'])->default('pending');

            $table->json('pickup_location');
            $table->json('dropoff_location');
            $table->string('pickup_address');
            $table->string('dropoff_address');

            $table->decimal('distance_km', 8, 2);
            $table->decimal('estimated_price', 10, 2);
            $table->decimal('final_price', 10, 2)->nullable();

            $table->enum('payment_method', ['cash', 'mobile_money', 'card'])->default('cash');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('cancellation_reason')->nullable();
            $table->enum('cancelled_by', ['client', 'driver', 'system'])->nullable();

            $table->decimal('platform_commission', 10, 2)->nullable();
            $table->decimal('driver_earnings', 10, 2)->nullable();

            $table->boolean('is_night_surcharge')->default(false);
            $table->boolean('is_airport_surcharge')->default(false);
            $table->boolean('is_long_distance')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
