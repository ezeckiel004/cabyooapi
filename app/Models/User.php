<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'avatar',
        'profile_picture',
        'notes',
        'total_spent',
        'ride_count',
        'car_model',
        'car_plate',
        'car_color',
        'year_of_experience',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Relations
    public function driverDetail()
    {
        return $this->hasOne(DriverDetail::class);
    }

    public function clientRides()
    {
        return $this->hasMany(Ride::class, 'client_id');
    }

    public function driverRides()
    {
        return $this->hasMany(Ride::class, 'driver_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Scopes
    public function scopeClients($query)
    {
        return $query->where('role', 'client');
    }

    public function scopeDrivers($query)
    {
        return $query->where('role', 'chauffeur');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Méthodes helpers
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isDriver()
    {
        return $this->role === 'chauffeur';
    }

    public function isClient()
    {
        return $this->role === 'client';
    }

    public function canBeDriver()
    {
        return in_array($this->role, ['client', 'chauffeur']);
    }
}
