<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scope pour les messages non lus
    public function scopeUnread($query)
    {
        return $query->where('status', 'pending');
    }

    // Scope pour les messages traités
    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }
}
