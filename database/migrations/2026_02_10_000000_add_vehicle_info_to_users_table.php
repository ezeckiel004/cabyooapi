<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ajouter les colonnes du véhicule si elles n'existent pas
            if (!Schema::hasColumn('users', 'car_model')) {
                $table->string('car_model')->nullable();
            }
            if (!Schema::hasColumn('users', 'car_plate')) {
                $table->string('car_plate')->nullable();
            }
            if (!Schema::hasColumn('users', 'car_color')) {
                $table->string('car_color')->nullable();
            }
            if (!Schema::hasColumn('users', 'year_of_experience')) {
                $table->integer('year_of_experience')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['car_model', 'car_plate', 'car_color', 'year_of_experience']);
        });
    }
};
