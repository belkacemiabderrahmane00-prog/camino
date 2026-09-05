<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Photo partagée par la communauté, stockée en base (redimensionnée), servie par /photos/{id}.
 */
class PlacePhoto extends Model
{
    protected $fillable = ['place_id', 'user_id', 'caption', 'mime', 'width', 'height', 'bytes', 'data', 'status'];

    protected $hidden = ['data'];

    /** pdo_pgsql renvoie les colonnes bytea sous forme de flux : on les lit en chaîne. */
    public function getDataAttribute($value): ?string
    {
        if (is_resource($value)) {
            rewind($value);
            $value = stream_get_contents($value);
            $this->attributes['data'] = $value;
        }

        return $value;
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return route('photos.show', $this);
    }
}
