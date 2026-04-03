<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    // Liste tous les paramètres
    public function index()
    {
        $settings = Setting::orderBy('group')->orderBy('key')->get();

        // Grouper par catégorie avec format key => {value, type}
        $grouped = [];
        foreach ($settings as $setting) {
            if (!isset($grouped[$setting->group])) {
                $grouped[$setting->group] = [];
            }
            $grouped[$setting->group][$setting->key] = [
                'value' => $setting->value,
                'type' => $setting->type,
                'id' => $setting->id
            ];
        }

        return response()->json($grouped);
    }

    // Paramètres par groupe
    public function byGroup($group)
    {
        $settings = Setting::where('group', $group)->get();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $this->castValue($setting->value, $setting->type);
        }

        return response()->json($result);
    }

    // Créer un paramètre
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|unique:settings',
            'value' => 'required',
            'group' => 'required|string',
            'type' => 'required|in:string,integer,float,boolean,array,json',
        ]);

        $setting = Setting::create($validated);

        return response()->json([
            'message' => 'Paramètre créé avec succès',
            'setting' => $setting
        ], 201);
    }

    // Mettre à jour un paramètre
    public function update(Request $request, Setting $setting)
    {
        $validated = $request->validate([
            'value' => 'required',
            'type' => 'sometimes|in:string,integer,float,boolean,array,json',
        ]);

        $setting->update($validated);

        return response()->json([
            'message' => 'Paramètre mis à jour avec succès',
            'setting' => $setting
        ]);
    }

    // Mettre à jour les tarifs
    public function updateTarifs(Request $request)
    {
        $validated = $request->validate([
            'price_per_km' => 'required|numeric|min:0',
            'min_price' => 'required|numeric|min:0',
            'night_surcharge_percent' => 'required|numeric|min:0|max:100',
            'airport_surcharge' => 'required|numeric|min:0',
            'long_distance_threshold_km' => 'required|numeric|min:0',
            'long_distance_surcharge_percent' => 'required|numeric|min:0|max:100',
            'platform_commission_percent' => 'required|numeric|min:0|max:100',
        ]);

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value, 'tarifs', 'float');
        }

        return response()->json([
            'message' => 'Tarifs mis à jour avec succès',
            'tarifs' => $validated
        ]);
    }

    // Mettre à jour les paramètres généraux
    public function updateGeneral(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string',
            'company_email' => 'required|email',
            'company_phone' => 'required|string',
            'company_address' => 'required|string',
        ]);

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value, 'general', 'string');
        }

        return response()->json([
            'message' => 'Paramètres généraux mis à jour avec succès',
            'general' => $validated
        ]);
    }

    // Mettre à jour les paramètres de paiement
    public function updatePayments(Request $request)
    {
        $validated = $request->validate([
            'cash_enabled' => 'required|boolean',
            'mobile_money_enabled' => 'required|boolean',
            'card_enabled' => 'required|boolean',
        ]);

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value, 'payments', 'boolean');
        }

        return response()->json([
            'message' => 'Paramètres de paiement mis à jour avec succès',
            'payments' => $validated
        ]);
    }

    // Mettre à jour les paramètres de notifications
    public function updateNotifications(Request $request)
    {
        $validated = $request->validate([
            'send_email_notifications' => 'required|boolean',
            'send_sms_notifications' => 'required|boolean',
            'send_push_notifications' => 'required|boolean',
        ]);

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value, 'notifications', 'boolean');
        }

        return response()->json([
            'message' => 'Paramètres de notifications mis à jour avec succès',
            'notifications' => $validated
        ]);
    }

    // Helper pour caster les valeurs
    private function castValue($value, $type)
    {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'array':
            case 'json':
                return json_decode($value, true) ?? $value;
            default:
                return $value;
        }
    }
}
