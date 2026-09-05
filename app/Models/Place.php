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

    /**
     * Vignette de couverture : pour les fichiers Wikimedia Commons en taille originale (souvent > 1 Mo),
     * on demande la miniature générée par Commons ; sinon l'URL telle quelle.
     */
    public function coverThumb(int $width = 800): ?string
    {
        $url = $this->cover_image_url;
        if (! $url) {
            return null;
        }
        // Commons ne sert plus que quelques largeurs prédéfinies : on prend la première ≥ à la largeur demandée.
        // Accepte l'URL du fichier original ou une URL de miniature existante (…/thumb/a/ab/Fichier/800px-Fichier).
        if (preg_match('~^https?://(?:upload|thumb)\.wikimedia\.org/wikipedia/commons/(?:thumb/)?([0-9a-f])/([0-9a-f]{2})/([^/?#]+)(?:/\d+px-[^/?#]+)?(?:\?.*)?$~i', $url, $m)) {
            $allowed = [250, 330, 500, 960, 1280];
            $chosen = $allowed[count($allowed) - 1];
            foreach ($allowed as $w) {
                if ($w >= $width) {
                    $chosen = $w;
                    break;
                }
            }

            return sprintf('https://upload.wikimedia.org/wikipedia/commons/thumb/%s/%s/%s/%dpx-%s', $m[1], $m[2], $m[3], $chosen, $m[3]);
        }

        return $url;
    }

    public function getCoverThumbAttribute(): ?string
    {
        return $this->coverThumb(800);
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
