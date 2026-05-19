<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Place;
use App\Models\User;

class Review extends Model
{
    protected $fillable = [
        'place_id',
        'user_id',
        'rating',
        'comment',
        'visited_at',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
