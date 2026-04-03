<?php
// app/Models/SupportTicket.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_type',
        'concern_type',
        'phone',
        'message',
        'attachment_path',
        'attachment_name',
        'status',
        'priority',
        'ip_address',
        'user_agent',
        'user_id' // Si l'utilisateur est connecté
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Mutateur pour le type d'utilisateur lisible
    public function getUserTypeLabelAttribute()
    {
        return match($this->user_type) {
            'passenger' => 'Passager',
            'driver' => 'Chauffeur',
            'partner' => 'Partenaire',
            default => 'Inconnu',
        };
    }

    // Mutateur pour le statut lisible
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'pending' => 'En attente',
            'in_progress' => 'En cours',
            'resolved' => 'Résolu',
            'closed' => 'Fermé',
            default => 'Inconnu',
        };
    }
}
