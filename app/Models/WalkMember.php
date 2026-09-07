<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkMember extends Model
{
    protected $fillable = ['walk_id', 'user_id', 'token', 'name', 'color', 'lat', 'lng', 'heading', 'accuracy', 'seen_at', 'arrived_at', 'left_at'];

    protected $casts = ['lat' => 'float', 'lng' => 'float', 'heading' => 'float', 'accuracy' => 'float', 'seen_at' => 'datetime', 'arrived_at' => 'datetime', 'left_at' => 'datetime'];

    protected $hidden = ['token'];

    public function walk(): BelongsTo
    {
        return $this->belongsTo(Walk::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOnline(): bool
    {
        return $this->left_at === null && $this->seen_at !== null && $this->seen_at->gt(now()->subSeconds(75));
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name)) ?: [];
        $ini = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $ini ?: '?';
    }
}
