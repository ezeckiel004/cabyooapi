<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        // Documents requis
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
        'vehicle_photo_file',
        // Infos chauffeur
        'driver_license',
        'car_model',
        'car_plate',
        'car_color',
        'car_year',
        'car_registration',
        'car_seats',
        'car_type',
        'car_insurance',
        'car_insurance_expiry',
        'car_inspection_expiry',
        'year_of_experience',
        'total_earnings',
        'completed_rides',
        'rating',
        'rating_count',
        'is_available',
        'working_hours',
        'base_price',
        'price_per_km',
        // 🔴 NOUVEAU: Géolocalisation
        'current_latitude',
        'current_longitude',
        'last_location_update',
        'location_enabled',
        // 💳 NOUVEAU: Préférences de paiement
        'payment_method',
        'bank_account_holder',
        'bank_iban',
        'bank_bic',
        'paypal_email',
        'stripe_id',
        'last_payment_date',
        'pending_balance',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'is_available' => 'boolean',
        'rating' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        // 🔴 NOUVEAU: Géolocalisation casts
        'current_latitude' => 'float',
        'current_longitude' => 'float',
        'location_enabled' => 'boolean',
        'last_location_update' => 'datetime',
        // 💳 NOUVEAU: Paiement casts
        'last_payment_date' => 'datetime',
        'pending_balance' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
