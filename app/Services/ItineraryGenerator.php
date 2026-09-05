<?php

namespace App\Services;

use App\Models\Place;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Générateur de parcours culturels.
 *
 * 1. Filtrage des candidats (coordonnées, gratuité, budget unitaire).
 * 2. Scoring : centres d'intérêt, thèmes, profil de l'utilisateur, météo (intérieur/extérieur),
 *    présence d'une photo et d'une description, note communautaire, proximité du départ.
 * 3. Sélection gloutonne dans le temps et le budget, avec diversité (max. 1 restaurant, 2 par catégorie).
 * 4. Ordre optimisé et trajets réels (Valhalla) ; ajustement si le temps réel dépasse.
 * 5. Horaires d'arrivée / départ à chaque étape, géométrie du tracé, résumé météo.
 */
class ItineraryGenerator
{
    private const INDOOR = ['musee', 'lieu-culturel', 'restauration'];

    private const OUTDOOR = ['parc-jardin', 'street-art', 'itineraire'];

    private const MAX_PER_CATEGORY = ['restauration' => 1, 'default' => 3];

    public function __construct(
        private readonly RoutingService $routing,
        private readonly WeatherService $weather,
    ) {}

    /**
     * @param Collection<int,Place> $candidates
     * @param array{
     *   time_budget_min:int, budget_eur?:?float, free_only?:bool, mode?:string,
     *   start?:array{lat:float,lng:float,label?:string}, starts_at?:Carbon,
     *   interests?:array<int,string>, tags?:array<int,string>, profile?:array<string,float>,
     *   preserve_order?:bool, max_steps?:int, use_weather?:bool
     * } $options
     */
    public function generate(Collection $candidates, array $options): array
    {
        $timeBudget = max(30, (int) ($options['time_budget_min'] ?? 180));
        $budgetEur = isset($options['budget_eur']) && $options['budget_eur'] !== null ? (float) $options['budget_eur'] : null;
        $freeOnly = (bool) ($options['free_only'] ?? false);
        $mode = in_array($options['mode'] ?? null, [RoutingService::MODE_WALK, RoutingService::MODE_BIKE], true) ? $options['mode'] : RoutingService::MODE_WALK;
        $startsAt = ($options['starts_at'] ?? null) instanceof Carbon ? $options['starts_at'] : Carbon::now(config('app.timezone'));
        $interests = array_values(array_filter((array) ($options['interests'] ?? [])));
        $tags = array_map(fn ($t) => mb_strtolower((string) $t), array_filter((array) ($options['tags'] ?? [])));
        $profile = (array) ($options['profile'] ?? []);
        $preserveOrder = (bool) ($options['preserve_order'] ?? false);
        $maxSteps = max(1, min(12, (int) ($options['max_steps'] ?? 7)));
        $useWeather = (bool) ($options['use_weather'] ?? true);

        $candidates = $candidates
            ->filter(fn (Place $p) => $p->lat && $p->lng)
            ->when($freeOnly, fn ($c) => $c->filter(fn (Place $p) => $p->is_free))
            ->values();

        $start = $options['start'] ?? null;
        if (! $start || ! isset($start['lat'], $start['lng'])) {
            $start = $candidates->isNotEmpty()
                ? ['lat' => (float) $candidates->avg('lat'), 'lng' => (float) $candidates->avg('lng'), 'label' => 'Point de départ']
                : ['lat' => config('camino.default_start.lat'), 'lng' => config('camino.default_start.lng'), 'label' => config('camino.default_start.label')];
        }
        $start['label'] = $start['label'] ?? 'Point de départ';

        $weather = $useWeather ? $this->weather->summaryFor((float) $start['lat'], (float) $start['lng'], $startsAt, $timeBudget) : null;

        if ($candidates->isEmpty()) {
            return $this->result($start, $startsAt, $mode, [], [], $weather, [
                $freeOnly ? 'Aucun lieu gratuit disponible autour du point de départ.' : 'Aucun lieu disponible autour du point de départ.',
            ]);
        }

        // --- 2. Scoring ---------------------------------------------------------------------------
        $scored = $candidates->map(function (Place $p) use ($start, $interests, $tags, $profile, $weather, $mode) {
            $slug = $p->category->slug ?? 'lieu-culturel';
            $score = 1.0;

            if ($interests !== []) {
                $score += in_array($slug, $interests, true) ? 2.5 : -1.0;
            }
            $placeTags = array_map(fn ($t) => mb_strtolower((string) $t), (array) ($p->tags ?? []));
            if ($tags !== [] && array_intersect($tags, $placeTags) !== []) {
                $score += 1.5;
            }
            $score += (float) ($profile[$slug] ?? 0) * 1.2;
            $score += $p->cover_image_url ? 0.9 : 0;
            $score += $p->description ? 0.3 : 0;
            $score += $p->reviews_avg_rating ? min(1.0, (float) $p->reviews_avg_rating / 5) : 0;

            if ($weather && $weather['indoor_recommended']) {
                $score += in_array($slug, self::INDOOR, true) ? 1.5 : (in_array($slug, self::OUTDOOR, true) ? -2.0 : 0);
            } elseif ($weather && ! $weather['indoor_recommended'] && in_array($slug, ['parc-jardin', 'street-art'], true)) {
                $score += 0.5;
            }

            $distanceKm = $this->routing->haversineKm((float) $start['lat'], (float) $start['lng'], (float) $p->lat, (float) $p->lng);
            $score -= $distanceKm * ($mode === RoutingService::MODE_BIKE ? 0.15 : 0.45);

            return ['place' => $p, 'score' => $score, 'distance_km' => $distanceKm, 'slug' => $slug];
        });

        // --- 3. Sélection gloutonne -------------------------------------------------------------
        $ordered = $preserveOrder ? $scored : $scored->sortByDesc('score')->values();
        $selected = [];
        $usedMinutes = 0;
        $usedEur = 0.0;
        $perCategory = [];
        $skippedBudget = 0;
        $curLat = (float) $start['lat'];
        $curLng = (float) $start['lng'];

        foreach ($ordered as $row) {
            /** @var Place $place */
            $place = $row['place'];
            if (count($selected) >= $maxSteps) {
                break;
            }
            $cap = self::MAX_PER_CATEGORY[$row['slug']] ?? self::MAX_PER_CATEGORY['default'];
            if (! $preserveOrder && ($perCategory[$row['slug']] ?? 0) >= $cap) {
                continue;
            }
            $cost = $this->estimateCost($place);
            if ($budgetEur !== null && $usedEur + $cost > $budgetEur) {
                $skippedBudget++;

                continue;
            }
            $visit = (int) ($place->visit_duration_min ?: 60);
            $travel = $this->routing->estimateMinutes($curLat, $curLng, (float) $place->lat, (float) $place->lng, $mode);
            if ($usedMinutes + $travel + $visit > $timeBudget) {
                if ($preserveOrder) {
                    break;
                }

                continue;
            }
            $selected[] = $place;
            $usedMinutes += $travel + $visit;
            $usedEur += $cost;
            $perCategory[$row['slug']] = ($perCategory[$row['slug']] ?? 0) + 1;
            $curLat = (float) $place->lat;
            $curLng = (float) $place->lng;
        }

        if ($selected === []) {
            return $this->result($start, $startsAt, $mode, [], [], $weather, [
                $skippedBudget > 0 ? 'Le budget indiqué est trop faible pour les lieux disponibles.' : 'Le temps disponible est trop court pour proposer un parcours.',
            ]);
        }

        // --- 4. Ordre optimisé + trajets réels --------------------------------------------------
        $points = array_merge([['lat' => (float) $start['lat'], 'lng' => (float) $start['lng']]], array_map(fn (Place $p) => ['lat' => (float) $p->lat, 'lng' => (float) $p->lng], $selected));
        $route = $preserveOrder ? $this->routing->route($points, $mode) : $this->routing->optimizedRoute($points, $mode);
        $order = $route['order'] ?? array_keys($points);
        $sequence = [];
        foreach ($order as $idx) {
            if ($idx > 0 && isset($selected[$idx - 1])) {
                $sequence[] = $selected[$idx - 1];
            }
        }

        // Ajustement : si les durées réelles dépassent le temps, on retire les dernières étapes.
        $legs = $route['legs'] ?? [];
        $trimmed = false;
        while (count($sequence) > 1 && $this->totalMinutes($sequence, $legs) > $timeBudget) {
            array_pop($sequence);
            array_pop($legs);
            $trimmed = true;
        }
        if ($trimmed) {
            $points = array_merge([['lat' => (float) $start['lat'], 'lng' => (float) $start['lng']]], array_map(fn (Place $p) => ['lat' => (float) $p->lat, 'lng' => (float) $p->lng], $sequence));
            $route = $this->routing->route($points, $mode);
            $legs = $route['legs'] ?? [];
        }

        // --- 5. Étapes horodatées ---------------------------------------------------------------
        $steps = [];
        $cursor = $startsAt->copy();
        $totalCost = 0.0;
        foreach ($sequence as $i => $place) {
            $leg = $legs[$i] ?? ['distance_km' => 0, 'duration_min' => 0];
            $visit = (int) ($place->visit_duration_min ?: 60);
            $cost = $this->estimateCost($place);
            $totalCost += $cost;
            $arrive = $cursor->copy()->addMinutes((int) $leg['duration_min']);
            $leave = $arrive->copy()->addMinutes($visit);
            $steps[] = [
                'order' => $i + 1,
                'place_id' => $place->id,
                'title' => $place->title,
                'address' => $place->address,
                'category' => $place->category->name ?? null,
                'category_slug' => $place->category->slug ?? null,
                'cover' => $place->coverThumb(480),
                'lat' => (float) $place->lat,
                'lng' => (float) $place->lng,
                'is_free' => (bool) $place->is_free,
                'price_level' => $place->price_level,
                'cost_eur' => round($cost, 2),
                'visit_minutes' => $visit,
                'travel_minutes' => (int) $leg['duration_min'],
                'travel_km' => round((float) $leg['distance_km'], 2),
                'arrive_at' => $arrive->format('H:i'),
                'leave_at' => $leave->format('H:i'),
                'reason' => $this->reason($place, $interests, $weather),
            ];
            $cursor = $leave;
        }

        $warnings = [];
        if ($skippedBudget > 0) {
            $warnings[] = $skippedBudget . ' lieu(x) écarté(s) pour rester dans le budget.';
        }
        if (($route['source'] ?? '') === 'estimate') {
            $warnings[] = 'Service de routage indisponible : distances estimées à vol d\'oiseau.';
        }
        if ($weather && $weather['indoor_recommended']) {
            $warnings[] = sprintf('Météo : %s (%d %% de pluie) — le parcours privilégie les lieux couverts.', $weather['label'], $weather['rain_probability']);
        }

        return $this->result($start, $startsAt, $mode, $steps, $route, $weather, $warnings, round($totalCost, 2));
    }

    public function estimateCost(Place $place): float
    {
        if ($place->is_free) {
            return 0.0;
        }

        return match ((int) ($place->price_level ?? 0)) {
            1 => 5.0,
            2 => 15.0,
            3 => 30.0,
            default => 0.0,
        };
    }

    /** @param array<int,Place> $sequence */
    private function totalMinutes(array $sequence, array $legs): int
    {
        $total = 0;
        foreach ($sequence as $i => $place) {
            $total += (int) (($legs[$i]['duration_min'] ?? 0)) + (int) ($place->visit_duration_min ?: 60);
        }

        return $total;
    }

    private function reason(Place $place, array $interests, ?array $weather): string
    {
        $slug = $place->category->slug ?? '';
        if ($interests !== [] && in_array($slug, $interests, true)) {
            return 'Correspond à tes centres d\'intérêt';
        }
        if ($weather && $weather['indoor_recommended'] && in_array($slug, self::INDOOR, true)) {
            return 'À l\'abri en cas de pluie';
        }
        if ($place->is_free) {
            return 'Gratuit';
        }
        if ($place->reviews_avg_rating) {
            return 'Bien noté par la communauté';
        }

        return 'Proche de ton parcours';
    }

    private function result(array $start, Carbon $startsAt, string $mode, array $steps, array $route, ?array $weather, array $warnings, float $totalCost = 0.0): array
    {
        $totalMinutes = array_sum(array_map(fn ($s) => $s['visit_minutes'] + $s['travel_minutes'], $steps));
        $categories = array_values(array_unique(array_filter(array_map(fn ($s) => $s['category'], $steps))));

        return [
            'version' => 2,
            'title' => $this->title($categories, $start['label'] ?? null),
            'mode' => $mode,
            'start' => ['lat' => (float) $start['lat'], 'lng' => (float) $start['lng'], 'label' => $start['label'] ?? 'Point de départ'],
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $startsAt->copy()->addMinutes($totalMinutes)->toIso8601String(),
            'total_minutes' => $totalMinutes,
            'total_distance_km' => round((float) ($route['distance_km'] ?? 0), 2),
            'total_cost_eur' => $totalCost,
            'steps' => $steps,
            'geometry' => $route['geometry'] ?? [],
            'routing_source' => $route['source'] ?? 'none',
            'weather' => $weather,
            'warnings' => $warnings,
        ];
    }

    private function title(array $categories, ?string $startLabel): string
    {
        if ($categories === []) {
            return 'Parcours CAMINO';
        }
        $main = array_slice($categories, 0, 2);
        $label = implode(' & ', array_map(fn ($c) => mb_strtolower($c), $main));

        return 'Balade ' . $label . ($startLabel && $startLabel !== 'Point de départ' ? ' · ' . $startLabel : '');
    }
}
