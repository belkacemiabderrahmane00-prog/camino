<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Place;
use App\Services\FreeSundayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PoiController extends Controller
{
    private const WALK_M_PER_MIN = 78; // ~4,7 km/h

    /** Mots de la recherche « intelligente » → filtre (la recherche comprend « musée gratuit marais »). */
    private const SEARCH_WORDS = [
        'gratuit' => ['free' => true], 'free' => ['free' => true], 'ouvert' => ['open_now' => true], 'open' => ['open_now' => true],
        'musée' => ['slug' => 'musee'], 'musee' => ['slug' => 'musee'], 'museum' => ['slug' => 'musee'], 'musées' => ['slug' => 'musee'],
        'monument' => ['slug' => 'monument'], 'monuments' => ['slug' => 'monument'],
        'parc' => ['slug' => 'parc-jardin'], 'parcs' => ['slug' => 'parc-jardin'], 'jardin' => ['slug' => 'parc-jardin'], 'park' => ['slug' => 'parc-jardin'], 'garden' => ['slug' => 'parc-jardin'],
        'galerie' => ['slug' => 'lieu-culturel'], 'théâtre' => ['slug' => 'lieu-culturel'], 'theatre' => ['slug' => 'lieu-culturel'], 'cinéma' => ['slug' => 'lieu-culturel'],
        'street' => ['slug' => 'street-art'], 'graffiti' => ['slug' => 'street-art'], 'fresque' => ['slug' => 'street-art'],
        'restaurant' => ['slug' => 'restauration'], 'resto' => ['slug' => 'restauration'], 'café' => ['slug' => 'restauration'], 'manger' => ['slug' => 'restauration'],
        'librairie' => ['slug' => 'librairies-bibliotheques'], 'bibliothèque' => ['slug' => 'librairies-bibliotheques'], 'bookshop' => ['slug' => 'librairies-bibliotheques'], 'library' => ['slug' => 'librairies-bibliotheques'],
        'atelier' => ['slug' => 'ateliers-artisans'], 'artisan' => ['slug' => 'ateliers-artisans'], 'workshop' => ['slug' => 'ateliers-artisans'],
        'événement' => ['events' => true], 'evenement' => ['events' => true], 'concert' => ['events' => true], 'festival' => ['events' => true], 'expo' => ['events' => true], 'exposition' => ['events' => true],
        'balade' => ['slug' => 'itineraire'], 'promenade' => ['slug' => 'itineraire'],
    ];

    /**
     * GET /api/v1/pois
     * bbox, categories, category_slugs, tags, q (recherche intelligente), free, price_max, events, limit,
     * open_now, at=HH:MM (+ date=YYYY-MM-DD), rated, free_sunday, lat/lng (position : distance et temps à pied), near_m, sort=rating|distance|recent
     */
    public function index(Request $request, FreeSundayService $freeSunday): JsonResponse
    {
        $query = Place::query()
            ->with(['category', 'translations' => fn ($q) => $q->where('locale', app()->getLocale())->where('field', 'description')])
            ->withAvg('reviews', 'rating')
            ->withCount(['alerts as active_alerts_count' => fn ($q) => $q->active()])
            ->visible();

        $lat = $request->filled('lat') ? (float) $request->get('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->get('lng') : null;
        $nearM = (int) $request->get('near_m', 0);

        if ($nearM > 0 && $lat !== null && $lng !== null) {
            $dLat = $nearM / 111320;
            $dLng = $nearM / (111320 * max(0.2, cos(deg2rad($lat))));
            $query->whereBetween('lat', [$lat - $dLat, $lat + $dLat])->whereBetween('lng', [$lng - $dLng, $lng + $dLng]);
        } elseif ($bbox = $request->string('bbox')->toString()) {
            $coords = array_map('floatval', explode(',', $bbox));
            if (count($coords) === 4) {
                [$minLat, $minLng, $maxLat, $maxLng] = $coords;
                $query->whereBetween('lat', [$minLat, $maxLat])->whereBetween('lng', [$minLng, $maxLng]);
            }
        }

        $slugList = array_filter(array_map('trim', explode(',', $request->string('category_slugs')->toString())));
        $free = $request->boolean('free');
        $events = $request->boolean('events');
        $openNow = $request->boolean('open_now');

        // Recherche « intelligente » : les mots connus deviennent des filtres, le reste cherche dans les titres et adresses.
        $terms = [];
        if ($search = trim($request->string('q')->toString())) {
            foreach (preg_split('/\s+/u', mb_strtolower($search)) as $word) {
                $rule = self::SEARCH_WORDS[$word] ?? null;
                if ($rule === null) {
                    if (! in_array($word, ['à', 'a', 'de', 'du', 'des', 'le', 'la', 'les', 'un', 'une', 'dans', 'en', 'the', 'in', 'et'], true)) {
                        $terms[] = $word;
                    }

                    continue;
                }
                if (isset($rule['slug'])) {
                    $slugList[] = $rule['slug'];
                }
                $free = $free || ! empty($rule['free']);
                $events = $events || ! empty($rule['events']);
                $openNow = $openNow || ! empty($rule['open_now']);
            }
            if ($terms !== []) {
                $query->search(implode(' ', $terms));
            }
        }

        if ($categories = $request->string('categories')->toString()) {
            $ids = array_filter(array_map('intval', explode(',', $categories)));
            if ($ids !== []) {
                $query->whereIn('category_id', $ids);
            }
        }
        if ($slugList !== []) {
            $categoryIds = Category::query()->whereIn('slug', array_unique($slugList))->pluck('id')->all();
            if ($categoryIds !== []) {
                $query->whereIn('category_id', $categoryIds);
            }
        }
        if ($tags = $request->string('tags')->toString()) {
            foreach (array_filter(array_map('trim', explode(',', $tags))) as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }
        if ($events) {
            $query->whereNotNull('event_end_at')->orderBy('event_start_at');
        }
        if ($request->boolean('free_sunday')) {
            $freeSunday->scope($query);
        } elseif ($free) {
            $query->where('is_free', true);
        } elseif (($priceMax = (int) $request->get('price_max')) >= 1 && $priceMax <= 3) {
            $query->where(fn ($q) => $q->where('is_free', true)->orWhere('price_level', '<=', $priceMax));
        }
        if ($request->boolean('rated')) {
            $query->has('reviews');
        }

        $sort = (string) $request->get('sort', $events ? 'events' : 'recent');
        if ($sort === 'rating') {
            $query->orderByDesc('reviews_avg_rating');
        } elseif ($sort !== 'events') {
            $query->orderByRaw('(cover_image_url is null) asc')->latest();
        }

        // Horaires : maintenant, ou à une heure choisie (curseur « à quelle heure ? »).
        $tz = config('app.timezone');
        $at = Carbon::now($tz);
        if ($request->filled('at') && preg_match('/^(\d{1,2}):(\d{2})$/', (string) $request->get('at'), $m)) {
            $day = $request->filled('date') ? Carbon::parse((string) $request->get('date'), $tz) : Carbon::now($tz);
            $at = $day->copy()->setTime((int) $m[1], (int) $m[2]);
        }
        $filterOpen = $openNow || $request->filled('at');

        $limit = min((int) $request->get('limit', 100), 200);
        $items = $query->take($filterOpen ? min(400, $limit * 3) : $limit)->get();

        $rows = [];
        foreach ($items as $place) {
            $row = $this->formatPoi($place, $at, $lat, $lng, $freeSunday);
            if ($filterOpen && $row['hours']['open'] === false) {
                continue;
            }
            if ($request->boolean('rated') && ($row['rating'] === null || $row['rating'] < 4)) {
                continue;
            }
            $rows[] = $row;
            if (count($rows) >= $limit) {
                break;
            }
        }
        if ($sort === 'distance' && $lat !== null) {
            usort($rows, fn ($a, $b) => ($a['distance_m'] ?? PHP_INT_MAX) <=> ($b['distance_m'] ?? PHP_INT_MAX));
        }

        return response()->json([
            'data' => $rows,
            'meta' => ['at' => $at->format('H:i'), 'first_sunday' => $freeSunday->isFirstSunday(Carbon::now($tz)), 'next_first_sunday' => $freeSunday->nextFirstSunday()->toDateString(), 'next_first_sunday_label' => $freeSunday->label()],
        ]);
    }

    /**
     * GET /api/v1/poi/{id}
     */
    public function show(int $id, Request $request, FreeSundayService $freeSunday): JsonResponse
    {
        $place = Place::query()
            ->with(['category', 'translations' => fn ($q) => $q->where('locale', app()->getLocale())->where('field', 'description')])
            ->withAvg('reviews', 'rating')
            ->withCount(['alerts as active_alerts_count' => fn ($q) => $q->active()])
            ->visible()
            ->find($id);

        if (! $place) {
            return response()->json(['message' => 'POI not found'], 404);
        }
        $lat = $request->filled('lat') ? (float) $request->get('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->get('lng') : null;

        return response()->json(['data' => $this->formatPoi($place, Carbon::now(config('app.timezone')), $lat, $lng, $freeSunday)]);
    }

    private function formatPoi(Place $place, Carbon $at, ?float $lat, ?float $lng, FreeSundayService $freeSunday): array
    {
        $window = $place->hoursFor($at);
        // Le parseur renvoie les bornes en minutes depuis minuit (ou « HH:MM ») : on compare en minutes, on affiche en HH:MM.
        $toMin = function ($v): ?int {
            if ($v === null || $v === '') {
                return null;
            }
            if (is_string($v) && str_contains($v, ':')) {
                [$h, $m] = array_map('intval', explode(':', $v));

                return $h * 60 + $m;
            }

            return (int) $v;
        };
        $opensMin = $toMin($window['opens'] ?? null);
        $closesMin = $toMin($window['closes'] ?? null);
        $fmt = fn (?int $m) => $m === null ? null : sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60);
        $open = null;
        if ($window['status'] === 'open' && $opensMin !== null && $closesMin !== null) {
            $nowMin = $at->hour * 60 + $at->minute;
            $open = $nowMin >= $opensMin && $nowMin < $closesMin;
        } elseif ($window['status'] === 'closed') {
            $open = false;
        }
        $window['opens'] = $fmt($opensMin);
        $window['closes'] = $fmt($closesMin);
        $distance = null;
        if ($lat !== null && $lng !== null && $place->lat && $place->lng) {
            $x = deg2rad($place->lng - $lng) * cos(deg2rad(($place->lat + $lat) / 2));
            $y = deg2rad($place->lat - $lat);
            $distance = (int) round(sqrt($x * $x + $y * $y) * 6371000);
        }
        $translated = $place->translations->first()?->text;
        $description = $translated ?: $place->description;
        $isFreeSunday = ! $place->is_free && $freeSunday->appliesTo($place);

        return [
            'id' => $place->id,
            'title' => $place->title,
            'slug' => $place->slug,
            'description' => $place->description,
            'description_short' => $description ? Str::limit(trim(preg_replace('/\s+/u', ' ', $description)), 180) : null,
            'category' => $place->category ? [
                'id' => $place->category->id,
                'name' => __($place->category->name),
                'slug' => $place->category->slug,
            ] : null,
            'lat' => $place->lat,
            'lng' => $place->lng,
            'address' => $place->address,
            'is_free' => $place->is_free,
            'free_sunday' => $isFreeSunday,
            'accessible' => $place->accessible,
            'price_level' => $place->price_level,
            'visit_duration_min' => $place->visit_duration_min,
            'opening_hours' => $place->opening_hours,
            'hours' => ['status' => $window['status'], 'opens' => $window['opens'], 'closes' => $window['closes'], 'open' => $open, 'note' => $window['note'] ?? null],
            'distance_m' => $distance,
            'walk_min' => $distance !== null ? max(1, (int) round($distance * 1.25 / self::WALK_M_PER_MIN)) : null,
            'tags' => $place->tags ?? [],
            'media' => [
                'cover' => $place->coverThumb(640),
                'cover_large' => $place->coverThumb(1280),
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
