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
