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
            // Ajouter les champs de prix si ils n'existent pas
            if (!Schema::hasColumn('driver_details', 'base_price')) {
                $table->decimal('base_price', 8, 2)->nullable()->default(0)->after('is_available')->comment('Prix de base par trajet');
            }
            if (!Schema::hasColumn('driver_details', 'price_per_km')) {
                $table->decimal('price_per_km', 8, 2)->nullable()->default(0)->after('base_price')->comment('Prix par km');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'price_per_km']);
        });
    }
};
