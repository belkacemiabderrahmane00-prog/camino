<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Visite réelle d'un lieu : enregistrée par le guidage à l'arrivée, ou déclarée à la main sur la fiche.
 */
class Visit extends Model
{
    protected $fillable = ['user_id', 'place_id', 'itinerary_id', 'source', 'minutes', 'visited_at'];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function itinerary()
    {
        return $this->belongsTo(Itinerary::class);
    }
}
