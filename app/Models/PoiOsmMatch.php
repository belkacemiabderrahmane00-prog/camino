<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoiOsmMatch extends Model
{
    protected $fillable = [
        'place_id',
        'osm_type',
        'osm_id',
        'wikidata_qid',
        'match_score',
        'matched_at',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
        'match_score' => 'float',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class);
    }
}

