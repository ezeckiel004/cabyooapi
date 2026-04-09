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
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('email');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('amount_range'); // Plage du montant à investir
            $table->decimal('amount_min', 10, 2)->nullable(); // Montant min
            $table->decimal('amount_max', 10, 2)->nullable(); // Montant max
            $table->enum('status', ['pending', 'contacted', 'processed', 'archived'])->default('pending');
            $table->timestamp('contacted_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            // Index pour les recherches
            $table->index('email');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
