<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkMessage extends Model
{
    protected $fillable = ['walk_id', 'member_id', 'type', 'body', 'photo', 'photo_mime', 'photo_bytes', 'lat', 'lng'];

    protected $hidden = ['photo'];

    protected $casts = ['lat' => 'float', 'lng' => 'float'];

    /** pdo_pgsql renvoie les colonnes bytea sous forme de flux : on les lit en chaîne. */
    public function getPhotoAttribute($value): ?string
    {
        if (is_resource($value)) {
            rewind($value);
            $value = stream_get_contents($value);
            $this->attributes['photo'] = $value;
        }

        return $value;
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(WalkMember::class, 'member_id');
    }

    public function walk(): BelongsTo
    {
        return $this->belongsTo(Walk::class);
    }
}
