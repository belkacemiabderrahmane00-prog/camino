<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoiController extends Controller
{
    /**
     * GET /api/v1/pois
     * Query params:
     * - bbox (min_lat,min_lng,max_lat,max_lng)
     * - categories (ids comma-sep)
     * - category_slugs (slugs comma-sep)
     * - tags (comma-sep)
     * - q (search in title / address)
     * - free=1 (only free places)
 * - price_max (1..3: places free or with price_level <= value)
 * - price_max (1..3: places free or with price_level <= value)
     * - limit
     */
    public function index(Request $request): JsonResponse
    {
        $query = Place::query()
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->withCount(['alerts as active_alerts_count' => fn ($q) => $q->active()])
            ->visible();

        if ($bbox = $request->string('bbox')->toString()) {
            $coords = array_map('floatval', explode(',', $bbox));
            if (count($coords) === 4) {
                [$minLat, $minLng, $maxLat, $maxLng] = $coords;
                $query->whereBetween('lat', [$minLat, $maxLat])
                    ->whereBetween('lng', [$minLng, $maxLng]);
            }
        }

        if ($categories = $request->string('categories')->toString()) {
            $ids = array_filter(array_map('intval', explode(',', $categories)));
            if ($ids !== []) {
                $query->whereIn('category_id', $ids);
            }
        }

        if ($slugs = $request->string('category_slugs')->toString()) {
            $slugList = array_filter(array_map('trim', explode(',', $slugs)));
            if ($slugList !== []) {
                $categoryIds = Category::query()
                    ->whereIn('slug', $slugList)
                    ->pluck('id')
                    ->all();

                if ($categoryIds !== []) {
                    $query->whereIn('category_id', $categoryIds);
                }
            }
        }

        if ($tags = $request->string('tags')->toString()) {
            $tagsList = array_filter(array_map('trim', explode(',', $tags)));
            if ($tagsList !== []) {
                foreach ($tagsList as $tag) {
                    $query->whereJsonContains('tags', $tag);
                }
            }
        }

        if ($request->boolean('events')) {
            $query->whereNotNull('event_end_at')->orderBy('event_start_at');
        }

        if ($request->boolean('free')) {
            $query->where('is_free', true);
        } elseif (($priceMax = (int) $request->get('price_max')) >= 1 && $priceMax <= 3) {
            $query->where(function ($q) use ($priceMax) {
                $q->where('is_free', true)->orWhere('price_level', '<=', $priceMax);
            });
        }

        if ($search = trim($request->string('q')->toString())) {
            $query->search($search);
        }

        $limit = min((int) $request->get('limit', 100), 200);
        $items = $query->latest()->take($limit)->get();

        return response()->json([
            'data' => $items->map(fn (Place $place) => $this->formatPoi($place)),
        ]);
    }

    /**
     * GET /api/v1/poi/{id}
     */
    public function show(int $id): JsonResponse
    {
        $place = Place::query()
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->visible()
            ->find($id);

        if (! $place) {
            return response()->json(['message' => 'POI not found'], 404);
        }

        return response()->json([
            'data' => $this->formatPoi($place),
        ]);
    }

    private function formatPoi(Place $place): array
    {
        return [
            'id' => $place->id,
            'title' => $place->title,
            'slug' => $place->slug,
            'description' => $place->description,
            'category' => $place->category ? [
                'id' => $place->category->id,
                'name' => __($place->category->name),
                'slug' => $place->category->slug,
            ] : null,
            'lat' => $place->lat,
            'lng' => $place->lng,
            'address' => $place->address,
            'is_free' => $place->is_free,
            'accessible' => $place->accessible,
            'price_level' => $place->price_level,
            'visit_duration_min' => $place->visit_duration_min,
            'opening_hours' => $place->opening_hours,
            'tags' => $place->tags ?? [],
            'media' => [
                'cover' => $place->coverThumb(640),
                'cover_original' => $place->cover_image_url,
                'gallery' => $place->gallery ?? [],
            ],
            'sources' => $place->sources ?? [],
            'rating' => $place->reviews_avg_rating ? round((float) $place->reviews_avg_rating, 1) : null,
            'event' => $place->event_end_at ? ['start' => $place->event_start_at?->toDateString(), 'end' => $place->event_end_at->toDateString()] : null,
            'alerts' => (int) ($place->active_alerts_count ?? 0),
        ];
    }
}
