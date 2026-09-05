<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Alerte communautaire façon Waze : événement gratuit, affluence, fermeture exceptionnelle, info.
 */
class PlaceAlert extends Model
{
    public const TYPES = [
        'free_event' => ['label' => 'Événement gratuit', 'icon' => 'celebration', 'color' => '#F59E0B', 'hours' => 24],
        'crowd' => ['label' => 'Forte affluence', 'icon' => 'groups', 'color' => '#EF4444', 'hours' => 3],
        'closure' => ['label' => 'Fermeture exceptionnelle', 'icon' => 'door_front', 'color' => '#6B7280', 'hours' => 24],
        'info' => ['label' => 'Bon plan', 'icon' => 'lightbulb', 'color' => '#0EA5E9', 'hours' => 12],
    ];

    protected $fillable = [
        'place_id', 'user_id', 'type', 'title', 'message', 'lat', 'lng', 'starts_at', 'expires_at', 'status',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label'] ?? 'Alerte';
    }

    public function getTypeIconAttribute(): string
    {
        return self::TYPES[$this->type]['icon'] ?? 'campaign';
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPES[$this->type]['color'] ?? '#0EA5E9';
    }
}
