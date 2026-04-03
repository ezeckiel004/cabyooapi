<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            // Ajouter les colonnes si elles n'existent pas
            if (!Schema::hasColumn('driver_details', 'kbis_file')) {
                $table->string('kbis_file')->nullable();
            }
            if (!Schema::hasColumn('driver_details', 'kbis_expiry')) {
                $table->date('kbis_expiry')->nullable();
            }

            if (!Schema::hasColumn('driver_details', 'vtc_registration_file')) {
                $table->string('vtc_registration_file')->nullable();
            }
            if (!Schema::hasColumn('driver_details', 'vtc_registration_expiry')) {
                $table->date('vtc_registration_expiry')->nullable();
            }

            if (!Schema::hasColumn('driver_details', 'rcpro_insurance_file')) {
                $table->string('rcpro_insurance_file')->nullable();
            }
            if (!Schema::hasColumn('driver_details', 'rcpro_insurance_expiry')) {
                $table->date('rcpro_insurance_expiry')->nullable();
            }

            if (!Schema::hasColumn('driver_details', 'vehicle_insurance_file')) {
                $table->string('vehicle_insurance_file')->nullable();
            }
            if (!Schema::hasColumn('driver_details', 'vehicle_insurance_expiry')) {
                $table->date('vehicle_insurance_expiry')->nullable();
            }

            if (!Schema::hasColumn('driver_details', 'vtc_card_file')) {
                $table->string('vtc_card_file')->nullable();
            }
            if (!Schema::hasColumn('driver_details', 'vtc_card_expiry')) {
                $table->date('vtc_card_expiry')->nullable();
            }

            if (!Schema::hasColumn('driver_details', 'driver_license_file')) {
                $table->string('driver_license_file')->nullable();
            }
            if (!Schema::hasColumn('driver_details', 'driver_license_expiry')) {
                $table->date('driver_license_expiry')->nullable();
            }

            if (!Schema::hasColumn('driver_details', 'car_registration_file')) {
                $table->string('car_registration_file')->nullable();
            }
            if (!Schema::hasColumn('driver_details', 'car_registration_expiry')) {
                $table->date('car_registration_expiry')->nullable();
            }

            if (!Schema::hasColumn('driver_details', 'identity_card_file')) {
                $table->string('identity_card_file')->nullable();
            }
            if (!Schema::hasColumn('driver_details', 'identity_card_expiry')) {
                $table->date('identity_card_expiry')->nullable();
            }

            if (!Schema::hasColumn('driver_details', 'vehicle_photo_file')) {
                $table->string('vehicle_photo_file')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $columnsToDropIfExist = [
                'kbis_file',
                'kbis_expiry',
                'vtc_registration_file',
                'vtc_registration_expiry',
                'rcpro_insurance_file',
                'rcpro_insurance_expiry',
                'vehicle_insurance_file',
                'vehicle_insurance_expiry',
                'vtc_card_file',
                'vtc_card_expiry',
                'driver_license_file',
                'driver_license_expiry',
                'car_registration_file',
                'car_registration_expiry',
                'identity_card_file',
                'identity_card_expiry',
                'vehicle_photo_file',
            ];

            $columnsToActuallyDrop = [];
            foreach ($columnsToDropIfExist as $column) {
                if (Schema::hasColumn('driver_details', $column)) {
                    $columnsToActuallyDrop[] = $column;
                }
            }

            if (!empty($columnsToActuallyDrop)) {
                $table->dropColumn($columnsToActuallyDrop);
            }
        });
    }
};
