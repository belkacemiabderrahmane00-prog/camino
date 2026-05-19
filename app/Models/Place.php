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
    ];

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
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
