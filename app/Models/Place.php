<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\PlaceReport;
use App\Models\Review;
use App\Models\PoiMedia;
use App\Models\PoiOsmMatch;

class Place extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'category_id',
        'lat',
        'lng',
        'address',
        'status',
        'is_free',
        'price_level',
        'visit_duration_min',
        'opening_hours',
        'tags',
        'cover_image_url',
        'cover_image_source',
        'cover_image_license',
        'cover_image_author',
        'cover_image_attribution',
        'cover_image_page_url',
        'gallery',
        'sources',
        'external_id',
        'wikidata_qid',
        'cover_image_is_fallback',
        'cover_image_fallback_reason',
        'event_start_at',
        'event_end_at',
        'created_by',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'opening_hours' => 'array',
        'tags' => 'array',
        'gallery' => 'array',
        'sources' => 'array',
        'lat' => 'float',
        'lng' => 'float',
        'cover_image_is_fallback' => 'boolean',
        'event_start_at' => 'date',
        'event_end_at' => 'date',
    ];

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /** Visible au public : approuvé et, pour un événement daté, pas encore terminé. */
    public function scopeVisible($query)
    {
        return $query->approved()->where(function ($q) {
            $q->whereNull('event_end_at')->orWhereDate('event_end_at', '>=', now()->toDateString());
        });
    }

    public function scopeUpcomingEvents($query)
    {
        return $query->approved()->whereNotNull('event_end_at')->whereDate('event_end_at', '>=', now()->toDateString())->orderBy('event_start_at');
    }

    /**
     * Recherche insensible à la casse sur le titre et l'adresse (MySQL, Postgres, SQLite).
     */
    public function scopeSearch($query, string $term)
    {
        $like = '%' . mb_strtolower(trim($term)) . '%';

        return $query->where(function ($q) use ($like) {
            $q->whereRaw('LOWER(title) LIKE ?', [$like])
                ->orWhereRaw("LOWER(COALESCE(address, '')) LIKE ?", [$like]);
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function reports()
    {
        return $this->hasMany(PlaceReport::class);
    }

    public function osmMatch()
    {
        return $this->hasOne(PoiOsmMatch::class);
    }

    public function media()
    {
        return $this->hasMany(PoiMedia::class);
    }

    public function alerts()
    {
        return $this->hasMany(PlaceAlert::class);
    }

    public function photos()
    {
        return $this->hasMany(PlacePhoto::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getIsEventAttribute(): bool
    {
        return $this->event_end_at !== null;
    }

    public function getGoogleMapsUrl(): ?string
    {
        $address = $this->address ?? $this->title ?? null;
        if ($this->lat && $this->lng) {
            return 'https://www.google.com/maps/dir/?api=1&destination=' . $this->lat . ',' . $this->lng . '&travelmode=driving';
        }
        if ($address && $address !== 'Adresse à venir') {
            return 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($address) . '&travelmode=driving';
        }
        return null;
    }

    public function getWazeUrl(): ?string
    {
        $address = $this->address ?? $this->title ?? null;
        if ($this->lat && $this->lng) {
            return 'https://waze.com/ul?ll=' . $this->lat . ',' . $this->lng . '&navigate=yes';
        }
        if ($address && $address !== 'Adresse à venir') {
            return 'https://waze.com/ul?q=' . urlencode($address) . '&navigate=yes';
        }
        return null;
    }
}
