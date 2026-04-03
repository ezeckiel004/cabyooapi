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
            // Ajouter les colonnes de documents si elles n'existent pas
            $columns = [
                'vehicle_photo_file',
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
            ];

            foreach ($columns as $column) {
                if (!Schema::hasColumn('driver_details', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $columns = [
                'vehicle_photo_file',
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
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('driver_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
