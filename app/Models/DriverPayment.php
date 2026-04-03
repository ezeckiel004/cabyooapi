<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverPayment extends Model
{
    use HasFactory;

    protected $table = 'driver_payments';

    protected $fillable = [
        'driver_id',
        'amount',
        'payment_method',
        'bank_account_holder',
        'bank_iban',
        'bank_bic',
        'paypal_email',
        'stripe_id',
        'proof_data',
        'proof_file',
        'admin_notes',
        'status',
        'paid_at',
        'verified_at',
        'admin_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
        'proof_data' => 'array',
    ];

    protected $dates = ['paid_at', 'verified_at', 'created_at', 'updated_at'];

    /**
     * Relation: Le chauffeur qui a reçu le paiement
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Relation: L'admin qui a effectué le paiement
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Accesseur: Obtenir le montant formaté
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2, ',', ' ') . '€';
    }

    /**
     * Accesseur: Obtenir la date de paiement formatée
     */
    public function getFormattedPaidAtAttribute()
    {
        return $this->paid_at ? $this->paid_at->format('d/m/Y H:i') : 'N/A';
    }

    /**
     * Scope: Paiements complétés
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Paiements en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Paiements d'un chauffeur
     */
    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    /**
     * Scope: Paiements par période
     */
    public function scopeByPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('paid_at', [$startDate, $endDate]);
    }

    /**
     * Marquer comme payé
     */
    public function markAsPaid($adminId, $adminNotes = null)
    {
        $this->update([
            'status' => 'completed',
            'paid_at' => now(),
            'verified_at' => now(),
            'admin_id' => $adminId,
            'admin_notes' => $adminNotes,
        ]);

        // Remettre les earnings du chauffeur à 0
        if ($this->driver && $this->driver->driverDetail) {
            $this->driver->driverDetail->update([
                'total_earnings' => 0,
                'last_payment_date' => now(),
            ]);
        }

        return $this;
    }
}
