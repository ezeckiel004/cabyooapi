<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            // 💳 Mode de paiement préféré (3 options)
            $table->enum('payment_method', [
                'bank_transfer',    // Virement bancaire
                'paypal',          // PayPal
                'stripe'           // Stripe
            ])->nullable()->default('bank_transfer')->after('rating_count');
            
            // 📱 Informations bancaires
            $table->string('bank_account_holder')->nullable()->after('payment_method')->comment('Titulaire du compte bancaire');
            $table->string('bank_iban')->nullable()->after('bank_account_holder')->comment('IBAN bancaire');
            $table->string('bank_bic')->nullable()->after('bank_iban')->comment('Code BIC/SWIFT');
            
            // � PayPal
            $table->string('paypal_email')->nullable()->after('bank_bic')->comment('Email PayPal');
            
            // 🔐 Stripe
            $table->string('stripe_id')->nullable()->after('paypal_email')->comment('ID Stripe ou email associé');
            
            // 📅 Infos de paiement
            $table->timestamp('last_payment_date')->nullable()->after('stripe_id')->comment('Date du dernier paiement');
            $table->decimal('pending_balance', 10, 2)->default(0)->after('last_payment_date')->comment('Solde à payer');
            
            // 🔐 Indices pour les requêtes
            $table->index('payment_method');
            $table->index('last_payment_date');
        });
    }

    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['last_payment_date']);
            $table->dropColumn([
                'payment_method',
                'bank_account_holder',
                'bank_iban',
                'bank_bic',
                'last_payment_date',
                'pending_balance'
            ]);
        });
    }
};
