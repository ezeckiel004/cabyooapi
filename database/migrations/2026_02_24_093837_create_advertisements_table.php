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
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Titre de l'annonce
            $table->text('description'); // Description
            $table->string('image')->nullable(); // Chemin de l'image
            $table->string('badge'); // Badge text (NOUVEAU, OFFRE, BONUS, etc)
            $table->string('badge_color')->default('#FCD34D'); // Couleur du badge (hex)
            $table->string('link'); // URL de redirection
            $table->boolean('is_active')->default(true); // Actif ou non
            $table->integer('order')->default(0); // Ordre d'affichage
            $table->timestamp('start_date')->nullable(); // Date de début
            $table->timestamp('end_date')->nullable(); // Date de fin
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
