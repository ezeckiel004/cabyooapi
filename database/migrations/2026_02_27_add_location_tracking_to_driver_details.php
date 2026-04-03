<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ajoute les colonnes de suivi de position pour les chauffeurs
     */
    public function up(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            // Localisation actuelle du chauffeur
            $table->decimal('current_latitude', 10, 8)->nullable()->after('is_available');
            $table->decimal('current_longitude', 11, 8)->nullable()->after('current_latitude');
            
            // Dernier mise à jour de la position
            $table->timestamp('last_location_update')->nullable()->after('current_longitude');
            
            // Status d'enregistrement de la position
            $table->boolean('location_enabled')->default(false)->after('last_location_update');
            
            // Index pour les recherches géospatiales
            $table->index(['current_latitude', 'current_longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropIndex(['current_latitude', 'current_longitude']);
            $table->dropColumn(['current_latitude', 'current_longitude', 'last_location_update', 'location_enabled']);
        });
    }
};
