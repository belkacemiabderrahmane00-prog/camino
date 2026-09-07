<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Balade à plusieurs : groupe éphémère autour d'un parcours, rejoint par lien ou QR code.
 */
class Walk extends Model
{
    public const PALETTE = ['#0F8B8D', '#FF5A3C', '#7C3AED', '#15803D', '#DB2777', '#B45309', '#1D4ED8', '#E11D48', '#0369A1', '#9A3412'];

    protected $fillable = ['code', 'title', 'itinerary_id', 'route_json', 'host_member_id', 'meeting_lat', 'meeting_lng', 'meeting_label', 'meeting_by', 'meeting_at', 'status', 'expires_at'];

    protected $casts = ['route_json' => 'array', 'meeting_at' => 'datetime', 'expires_at' => 'datetime', 'meeting_lat' => 'float', 'meeting_lng' => 'float'];

    public static function newCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function members(): HasMany
    {
        return $this->hasMany(WalkMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WalkMessage::class);
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function url(): string
    {
        return route('walks.show', $this->code);
    }

    /** Membre correspondant à la session courante (jeton d'invité) ou à l'utilisateur connecté. */
    public function memberFor(\Illuminate\Http\Request $request): ?WalkMember
    {
        $userId = \Illuminate\Support\Facades\Auth::id();
        $member = $userId ? $this->members()->where('user_id', $userId)->whereNull('left_at')->first() : null;
        if (! $member) {
            $token = $request->session()->get('walk_member_' . $this->id);
            $member = $token ? $this->members()->where('token', $token)->whereNull('left_at')->first() : null;
            if ($member && $userId && $member->user_id && $member->user_id !== $userId) {
                $member = null;
            }
        }
        if ($member && $member->token !== $request->session()->get('walk_member_' . $this->id)) {
            $request->session()->put('walk_member_' . $this->id, $member->token);
        }

        return $member;
    }

    /** Balade active passée en paramètre (?balade=code) dont le visiteur est membre. */
    public static function fromRequest(\Illuminate\Http\Request $request): ?self
    {
        $code = (string) $request->query('balade', '');
        if ($code === '') {
            return null;
        }
        $walk = static::where('code', $code)->first();

        return $walk && $walk->isActive() && $walk->memberFor($request) ? $walk : null;
    }
}
