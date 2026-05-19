<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceReport extends Model
{
    protected $fillable = ['place_id', 'user_id', 'reason', 'message'];

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
