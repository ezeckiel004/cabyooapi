<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // Champs pour l'acompte (dépôt) de 10%
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('final_price')->comment('Montant de l\'acompte (10% du prix estimé)');
            $table->enum('deposit_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->after('deposit_amount')->comment('Statut du paiement d\'acompte');
            $table->string('stripe_payment_intent_id')->nullable()->after('deposit_status')->comment('ID du Payment Intent Stripe');
            $table->string('stripe_payment_method_id')->nullable()->after('stripe_payment_intent_id')->comment('ID de la méthode de paiement Stripe');
            $table->timestamp('deposit_paid_at')->nullable()->after('stripe_payment_method_id')->comment('Date du paiement de l\'acompte');
            $table->text('payment_error')->nullable()->after('deposit_paid_at')->comment('Erreur de paiement (si applicable)');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_amount',
                'deposit_status',
                'stripe_payment_intent_id',
                'stripe_payment_method_id',
                'deposit_paid_at',
                'payment_error',
            ]);
        });
    }
};
