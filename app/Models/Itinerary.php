<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Itinerary extends Model
{
    protected $fillable = ['user_id', 'name', 'result_json'];

    protected $casts = [
        'result_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
