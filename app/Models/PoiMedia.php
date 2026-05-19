<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoiMedia extends Model
{
    protected $fillable = [
        'place_id',
        'source',
        'title',
        'image_url_original',
        'image_url_thumb',
        'license',
        'author',
        'attribution_url',
        'is_cover',
        'extra',
    ];

    protected $casts = [
        'is_cover' => 'boolean',
        'extra' => 'array',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class);
    }
}

