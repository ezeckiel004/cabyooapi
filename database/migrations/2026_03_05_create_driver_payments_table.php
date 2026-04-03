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
        Schema::create('driver_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 10, 2)->comment('Montant payé au chauffeur');
            
            // Méthode de paiement
            $table->enum('payment_method', ['bank_transfer', 'paypal', 'stripe'])->comment('Método de pago utilizado');
            
            // Détails spécifiques au type de paiement
            $table->string('bank_account_holder')->nullable()->comment('Nom titulaire compte bancaire');
            $table->string('bank_iban')->nullable()->comment('IBAN');
            $table->string('bank_bic')->nullable()->comment('BIC');
            $table->string('paypal_email')->nullable()->comment('Email PayPal');
            $table->string('stripe_id')->nullable()->comment('ID Stripe');
            
            // Preuve de paiement
            $table->longText('proof_data')->nullable()->comment('Données de paiement/preuve JSON');
            $table->string('proof_file')->nullable()->comment('Fichier de preuve (capture d\'écran, etc)');
            $table->text('admin_notes')->nullable()->comment('Notes de l\'admin');
            
            // Statuts et dates
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending')->comment('Statut du paiement');
            $table->timestamp('paid_at')->nullable()->comment('Date du paiement');
            $table->timestamp('verified_at')->nullable()->comment('Date de vérification');
            
            // Admin qui a effectué le paiement
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null')->comment('Admin qui a effectué le paiement');
            
            $table->timestamps();
            
            // Indexes pour les requêtes fréquentes
            $table->index('driver_id');
            $table->index('status');
            $table->index('paid_at');
            $table->index('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_payments');
    }
};
