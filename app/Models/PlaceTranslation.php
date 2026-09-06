<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Traduction automatique d'un champ texte d'un lieu (description), par langue, mise en cache en base.
 */
class PlaceTranslation extends Model
{
    protected $fillable = ['place_id', 'locale', 'field', 'text', 'provider'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
