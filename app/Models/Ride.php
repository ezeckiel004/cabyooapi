<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    protected $fillable = [
        'ride_number',
        'client_id',
        'driver_id',
        'status',
        'pickup_location',
        'dropoff_location',
        'pickup_address',
        'dropoff_address',
        'destination_address',
        'vehicle_type',
        'passengers_count',
        'pickup_date',
        'pickup_time',
        'distance_km',
        'estimated_price',
        'final_price',
        'payment_method',
        'payment_status',
        'scheduled_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by',
        'platform_commission',
        'driver_earnings',
        'is_night_surcharge',
        'is_airport_surcharge',
        'is_long_distance',
        'notes',
        'deposit_amount',
        'deposit_status',
        'stripe_payment_intent_id',
        'stripe_payment_method_id',
        'deposit_paid_at',
        'payment_error',
    ];

    protected $casts = [
        'pickup_location' => 'array',
        'dropoff_location' => 'array',
        'estimated_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'driver_earnings' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'deposit_paid_at' => 'datetime',
        'is_night_surcharge' => 'boolean',
        'is_airport_surcharge' => 'boolean',
        'is_long_distance' => 'boolean',
    ];

    // Relations
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month);
    }

    // Méthodes helpers
    public function calculateCommission()
    {
        // Logique de calcul de commission (à adapter selon vos besoins)
        if ($this->final_price) {
            $commissionRate = config('settings.platform_commission', 0.20); // 20% par défaut
            $this->platform_commission = $this->final_price * $commissionRate;
            $this->driver_earnings = $this->final_price - $this->platform_commission;
            $this->save();
        }
    }
}
