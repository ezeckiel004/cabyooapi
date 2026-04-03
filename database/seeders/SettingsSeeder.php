<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Tarifs
        Setting::setValue('price_per_km', 500, 'tarifs', 'float');
        Setting::setValue('min_price', 1000, 'tarifs', 'float');
        Setting::setValue('night_surcharge_percent', 20, 'tarifs', 'float');
        Setting::setValue('airport_surcharge', 500, 'tarifs', 'float');
        Setting::setValue('long_distance_threshold_km', 20, 'tarifs', 'float');
        Setting::setValue('long_distance_surcharge_percent', 10, 'tarifs', 'float');
        Setting::setValue('platform_commission_percent', 20, 'tarifs', 'float');

        // Général
        Setting::setValue('company_name', 'SIMCAR VTC', 'general', 'string');
        Setting::setValue('company_email', 'contact@simcar.com', 'general', 'string');
        Setting::setValue('company_phone', '+237 6XX XXX XXX', 'general', 'string');
        Setting::setValue('company_address', 'Yaoundé, Cameroun', 'general', 'string');

        // Notifications
        Setting::setValue('send_email_notifications', true, 'notifications', 'boolean');
        Setting::setValue('send_sms_notifications', false, 'notifications', 'boolean');
        Setting::setValue('send_push_notifications', true, 'notifications', 'boolean');

        // Paiements
        Setting::setValue('cash_enabled', true, 'payments', 'boolean');
        Setting::setValue('mobile_money_enabled', true, 'payments', 'boolean');
        Setting::setValue('card_enabled', false, 'payments', 'boolean');

        $this->command->info('Paramètres par défaut créés avec succès!');
    }
}
