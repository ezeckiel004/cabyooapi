<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('driver_license')->nullable();
            $table->string('car_model')->nullable();
            $table->string('car_plate')->nullable();
            $table->string('car_color')->nullable();
            $table->integer('year_of_experience')->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->integer('completed_rides')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);
            $table->boolean('is_available')->default(true);
            $table->json('working_hours')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_details');
    }
};
