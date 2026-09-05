<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'bio',
        'interests',
        'mobility',
        'city',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'avatar',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'interests' => 'array',
        ];
    }

    public function savedPlaces()
    {
        return $this->belongsToMany(Place::class, 'saved_places');
    }

    public function itineraries()
    {
        return $this->hasMany(Itinerary::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function alerts()
    {
        return $this->hasMany(PlaceAlert::class);
    }

    public function photos()
    {
        return $this->hasMany(PlacePhoto::class);
    }

    /** URL de la photo de profil (ou null : on affiche l'initiale). */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_mime ? route('users.avatar', [$this, 'v' => $this->updated_at?->timestamp]) : null;
    }

    public function getInitialAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }

    public function submittedPlaces()
    {
        return $this->hasMany(Place::class, 'created_by');
    }
}
