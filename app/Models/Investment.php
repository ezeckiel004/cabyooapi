<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Investment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'address',
        'city',
        'amount_range',
        'amount_min',
        'amount_max',
        'status',
        'contacted_at',
        'admin_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'contacted_at' => 'datetime',
        'amount_min' => 'decimal:2',
        'amount_max' => 'decimal:2',
    ];

    /**
     * Get the full name of the investor.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the amount range formatted.
     */
    public function getAmountRangeFormattedAttribute(): string
    {
        $ranges = [
            '5000-10000' => '5 000 € - 10 000 €',
            '10000-25000' => '10 000 € - 25 000 €',
            '25000-50000' => '25 000 € - 50 000 €',
            '50000-100000' => '50 000 € - 100 000 €',
            '100000+' => 'Plus de 100 000 €',
        ];

        return $ranges[$this->amount_range] ?? $this->amount_range;
    }

    /**
     * Scope for pending investments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for contacted investments.
     */
    public function scopeContacted($query)
    {
        return $query->where('status', 'contacted');
    }

    /**
     * Scope for processed investments.
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }
}
