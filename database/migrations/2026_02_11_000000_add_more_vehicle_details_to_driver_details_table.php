<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            // Ajouter les colonnes manquantes pour les détails du véhicule
            $table->integer('car_year')->nullable()->after('car_color');
            $table->string('car_registration')->nullable()->after('car_year');
            $table->integer('car_seats')->nullable()->default(4)->after('car_registration');
            $table->string('car_type')->nullable()->after('car_seats'); // berline, SUV, monospace, etc.
            $table->string('car_insurance')->nullable()->after('car_type');
            $table->string('car_insurance_expiry')->nullable()->after('car_insurance');
            $table->string('car_inspection_expiry')->nullable()->after('car_insurance_expiry');
            $table->string('driver_license_expiry')->nullable()->after('driver_license');
        });
    }

    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropColumn([
                'car_year',
                'car_registration',
                'car_seats',
                'car_type',
                'car_insurance',
                'car_insurance_expiry',
                'car_inspection_expiry',
                'driver_license_expiry',
            ]);
        });
    }
};
