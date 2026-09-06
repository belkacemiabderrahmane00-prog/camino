<?php

namespace App\Services;

use App\Models\Place;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Générateur de parcours culturels (v3).
 *
 * 1. Candidats : coordonnées, gratuité, budget, horaires d'ouverture à la date choisie, alertes de fermeture.
 * 2. Scoring : intérêts, thèmes, profil, météo (intérieur/extérieur), photo, note, événements du jour, affluence.
 * 3. Matrice de temps réels (Valhalla) entre départ, candidats retenus et arrivée.
 * 4. Planification : insertion au moindre détour sous contraintes (temps, budget, fenêtres horaires,
 *    part de trajet), puis amélioration 2-opt. Pause déjeuner optionnelle.
 * 5. Tracé réel dans l'ordre final, horaires à chaque étape (attente comprise), plan B en cas de pluie.
 */
class ItineraryGenerator
{
    private const INDOOR = ['musee', 'lieu-culturel', 'restauration', 'librairies-bibliotheques', 'ateliers-artisans'];

    private const OUTDOOR = ['parc-jardin', 'street-art', 'itineraire'];

    private const MAX_PER_CATEGORY = ['restauration' => 1, 'default' => 2];

    /** Part maximale du temps passée en trajet. */
    private const MAX_TRAVEL_SHARE = 0.5;

    /** Attente maximale acceptée devant un lieu pas encore ouvert. */
    private const MAX_WAIT_MIN = 25;

    /** Nombre de candidats envoyés à la matrice de temps. */
    private const SHORTLIST = 18;

    private bool $accessibleFlag = false;

    public function __construct(
        private readonly RoutingService $routing,
        private readonly WeatherService $weather,
        private readonly TransitService $transit,
    ) {}

    /**
     * @param Collection<int,Place> $candidates
     * @param array{
     *   time_budget_min:int, budget_eur?:?float, free_only?:bool, mode?:string,
     *   start?:array{lat:float,lng:float,label?:string}, end?:?array{lat:float,lng:float,label?:string}, loop?:bool,
     *   starts_at?:Carbon, interests?:array<int,string>, tags?:array<int,string>, profile?:array<string,float>,
     *   preserve_order?:bool, max_steps?:int, use_weather?:bool, with_lunch?:bool,
     *   restaurants?:Collection<int,Place>, alerts?:array<int,array<int,string>>
     * } $options
     */
    public function generate(Collection $candidates, array $options): array
    {
        $timeBudget = max(30, (int) ($options['time_budget_min'] ?? 180));
        $budgetEur = isset($options['budget_eur']) && $options['budget_eur'] !== null ? (float) $options['budget_eur'] : null;
        $freeOnly = (bool) ($options['free_only'] ?? false);
        $transit = ($options['mode'] ?? null) === 'transit';
        $mode = in_array($options['mode'] ?? null, [RoutingService::MODE_WALK, RoutingService::MODE_BIKE], true) ? $options['mode'] : RoutingService::MODE_WALK;
        $travelMode = $transit ? 'transit' : $mode;
        $startsAt = ($options['starts_at'] ?? null) instanceof Carbon ? $options['starts_at']->copy() : Carbon::now(config('app.timezone'));
        $interests = array_values(array_filter((array) ($options['interests'] ?? [])));
        $tags = array_map(fn ($t) => mb_strtolower((string) $t), array_filter((array) ($options['tags'] ?? [])));
        $profile = (array) ($options['profile'] ?? []);
        $preserveOrder = (bool) ($options['preserve_order'] ?? false);
        $maxSteps = max(1, min(12, (int) ($options['max_steps'] ?? 7)));
        $useWeather = (bool) ($options['use_weather'] ?? true);
        $withLunch = (bool) ($options['with_lunch'] ?? false);
        $alerts = (array) ($options['alerts'] ?? []);
        $restaurants = ($options['restaurants'] ?? null) instanceof Collection ? $options['restaurants'] : collect();
        $strictTime = (bool) ($options['strict_time'] ?? true);
        $visitOverrides = (array) ($options['visit_overrides'] ?? []);
        $required = array_map('intval', (array) ($options['required'] ?? []));
        $scoreAdjust = (array) ($options['score_adjust'] ?? []);
        $jitter = (float) ($options['jitter'] ?? 0);
        $shortlistIds = array_map('intval', (array) ($options['shortlist_ids'] ?? []));
        $accessible = (bool) ($options['accessible'] ?? false);

        $start = $options['start'] ?? null;
        if (! $start || ! isset($start['lat'], $start['lng'])) {
            $start = $candidates->isNotEmpty()
                ? ['lat' => (float) $candidates->avg('lat'), 'lng' => (float) $candidates->avg('lng'), 'label' => 'Point de départ']
                : ['lat' => config('camino.default_start.lat'), 'lng' => config('camino.default_start.lng'), 'label' => config('camino.default_start.label')];
        }
        $start['label'] = $start['label'] ?? __('Point de départ');
        $loop = (bool) ($options['loop'] ?? false);
        $end = $options['end'] ?? null;
        if ($loop) {
            $end = ['lat' => (float) $start['lat'], 'lng' => (float) $start['lng'], 'label' => 'Retour au départ'];
        } elseif (! $end || ! isset($end['lat'], $end['lng'])) {
            $end = null;
        } else {
            $end['label'] = $end['label'] ?? __('Arrivée');
        }

        $weather = $useWeather ? $this->weather->summaryFor((float) $start['lat'], (float) $start['lng'], $startsAt, $timeBudget) : null;
        $date = $startsAt->copy()->startOfDay();
        $startMin = $startsAt->hour * 60 + $startsAt->minute;

        // --- 1. Candidats ---------------------------------------------------------------------------
        $closedCount = 0;
        $rows = [];
        foreach ($candidates as $p) {
            /** @var Place $p */
            if (! $p->lat || ! $p->lng) {
                continue;
            }
            if ($freeOnly && ! $p->is_free) {
                continue;
            }
            if (in_array('closure', $alerts[$p->id] ?? [], true)) {
                $closedCount++;

                continue;
            }
            if ($accessible && $p->accessible === false) {
                continue;
            }
            $hours = $p->hoursFor($date);
            if ($hours['status'] === 'closed') {
                $closedCount++;

                continue;
            }
            $rows[] = ['place' => $p, 'hours' => $hours, 'slug' => $p->category->slug ?? 'lieu-culturel'];
        }

        if ($rows === []) {
            $why = $closedCount > 0 ? __('Les lieux autour du départ sont fermés à cette date.') : ($freeOnly ? __('Aucun lieu gratuit disponible autour du point de départ.') : __('Aucun lieu disponible autour du point de départ.'));

            return $this->result($start, $end, $loop, $startsAt, $travelMode, [], [], $weather, [$why]);
        }

        // --- 2. Scoring ----------------------------------------------------------------------------
        foreach ($rows as &$row) {
            $p = $row['place'];
            $slug = $row['slug'];
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
            if ($row['hours']['status'] === 'unknown') {
                $score -= 0.3;
            }
            if ($accessible) {
                $score += $p->accessible === true ? 1.5 : -1.2;
            }
            if (in_array('crowd', $alerts[$p->id] ?? [], true)) {
                $score -= 1.0;
            }
            if ($p->event_start_at || $p->event_end_at) {
                $from = $p->event_start_at?->copy()->startOfDay();
                $to = $p->event_end_at?->copy()->endOfDay() ?? $from?->copy()->endOfDay();
                $score += ($from === null || $date->gte($from)) && ($to === null || $date->lte($to)) ? 1.2 : -3.0;
            }
            $distanceKm = $this->routing->haversineKm((float) $start['lat'], (float) $start['lng'], (float) $p->lat, (float) $p->lng);
            $score -= $distanceKm * ($mode === RoutingService::MODE_BIKE ? 0.15 : ($transit ? 0.12 : 0.45));
            $score += (float) ($scoreAdjust[$slug] ?? 0);
            if ($jitter > 0) {
                $score += (mt_rand(-1000, 1000) / 1000) * $jitter;
            }
            if (in_array((int) $p->id, $required, true)) {
                $score += 5.0;
            }
            $row['score'] = $score;
            $row['distance_km'] = $distanceKm;
            $row['visit'] = max(15, (int) ($visitOverrides[$p->id] ?? ($p->visit_duration_min ?: 60)));
            $row['required'] = in_array((int) $p->id, $required, true);
            $row['cost'] = $this->estimateCost($p);
            $row['kind'] = 'visit';
        }
        unset($row);

        // Liste courte pour la matrice de temps (le calcul TSP se fait dessus).
        if ($shortlistIds !== []) {
            $byId = [];
            foreach ($rows as $r) {
                $byId[(int) $r['place']->id] = $r;
            }
            $rows = array_values(array_filter(array_map(fn ($id) => $byId[$id] ?? null, $shortlistIds)));
        } elseif (! $preserveOrder) {
            usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);
            $keep = array_slice($rows, 0, self::SHORTLIST);
            foreach ($rows as $r) {
                if ($r['required'] && ! in_array($r, $keep, true)) {
                    $keep[] = $r;
                }
            }
            $rows = $keep;
        } else {
            $rows = array_slice($rows, 0, 15);
        }

        // Restaurants pour la pause déjeuner : ajoutés à la matrice, jamais choisis par le scoring.
        $lunchRows = [];
        if ($withLunch && ! $preserveOrder) {
            foreach ($restaurants->take(4) as $r) {
                if (! $r->lat || ! $r->lng || ($freeOnly && ! $r->is_free)) {
                    continue;
                }
                $hours = $r->hoursFor($date);
                if ($hours['status'] === 'closed') {
                    continue;
                }
                $lunchRows[] = ['place' => $r, 'hours' => $hours, 'slug' => 'restauration', 'score' => 0.0, 'distance_km' => 0.0, 'visit' => 60, 'cost' => $this->estimateCost($r) ?: 15.0, 'kind' => 'lunch'];
            }
        }

        // --- 3. Matrice de temps -----------------------------------------------------------------------
        $nodes = array_merge($rows, $lunchRows);
        $points = [['lat' => (float) $start['lat'], 'lng' => (float) $start['lng']]];
        foreach ($nodes as $n) {
            $points[] = ['lat' => (float) $n['place']->lat, 'lng' => (float) $n['place']->lng];
        }
        $endIdx = null;
        if ($end) {
            $points[] = ['lat' => (float) $end['lat'], 'lng' => (float) $end['lng']];
            $endIdx = count($points) - 1;
        }
        $matrix = $this->routing->matrix($points, $mode, $accessible);
        $T = $matrix['minutes'];
        $K = $matrix['km'];
        if ($transit) {
            // Estimation transports pour la planification (8 min d'accès/attente + ~15 km/h porte à porte) ; le vrai trajet vient ensuite de l'API.
            foreach ($T as $i => $row) {
                foreach ($row as $j => $walk) {
                    if ($i !== $j && ($K[$i][$j] ?? 0) > 1.2) {
                        $T[$i][$j] = (int) min($walk, 8 + round(($K[$i][$j] ?? 0) * 4));
                    }
                }
            }
        }

        $requiredIdx = [];
        foreach ($rows as $i => $r) {
            if ($r['required']) {
                $requiredIdx[] = $i + 1;
            }
        }
        $ctx = ['T' => $T, 'nodes' => $nodes, 'startMin' => $startMin, 'budget' => $timeBudget, 'endIdx' => $endIdx, 'required' => $requiredIdx];
        $lenient = $preserveOrder && ! $strictTime;

        // --- 4. Planification -----------------------------------------------------------------------
        $skippedBudget = 0;
        $trimmedForOrder = false;
        if ($preserveOrder) {
            $sequence = range(1, count($rows));
            while ($strictTime && $sequence !== [] && ! $this->simulate($sequence, $ctx)['feasible']) {
                array_pop($sequence);
                $trimmedForOrder = true;
            }
        } else {
            [$sequence, $skippedBudget, $ctx['nodes']] = $this->plan($rows, $ctx, $budgetEur, $maxSteps);
            $nodes = $ctx['nodes'];
            $sequence = $this->twoOpt($sequence, $ctx);
            if ($lunchRows !== []) {
                $sequence = $this->insertLunch($sequence, $ctx, count($rows), $budgetEur);
                // Le déjeuner a pu coûter une visite : on remplit le temps restant.
                [$sequence, $skippedAgain, $ctx['nodes']] = $this->plan($rows, $ctx, $budgetEur, $maxSteps + 1, $sequence);
                $nodes = $ctx['nodes'];
                $skippedBudget = max($skippedBudget, $skippedAgain);
            }
        }

        if ($sequence === []) {
            $closingSoon = count(array_filter($rows, fn ($r) => $r['hours']['status'] === 'open' && $r['hours']['closes'] !== null && $r['hours']['closes'] < $startMin + 45));
            $why = $skippedBudget > 0 ? __('Le budget indiqué est trop faible pour les lieux disponibles.') : __('Le temps disponible est trop court pour proposer un parcours avec ces lieux.');
            if ($closingSoon > 0 && $closingSoon >= count($rows) * 0.6) {
                $why = __('À cette heure, les lieux autour du départ sont fermés ou ferment bientôt. Choisis un autre créneau ou une autre date.');
            }

            return $this->result($start, $end, $loop, $startsAt, $mode, [], [], $weather, [$why]);
        }

        // --- 5. Tracé réel + horaires ------------------------------------------------------------------
        $routePoints = [$points[0]];
        foreach ($sequence as $idx) {
            $routePoints[] = $points[$idx];
        }
        if ($endIdx !== null) {
            $routePoints[] = $points[$endIdx];
        }
        $route = $this->routing->route($routePoints, $mode, $accessible);
        $legs = $route['legs'] ?? [];
        $applyLegs = function (array $legs) use (&$ctx, $sequence, $routePoints, $endIdx) {
            if (count($legs) === count($routePoints) - 1) {
                foreach ($sequence as $i => $idx) {
                    $prev = $i === 0 ? 0 : $sequence[$i - 1];
                    $ctx['T'][$prev][$idx] = (int) $legs[$i]['duration_min'];
                }
                if ($endIdx !== null) {
                    $ctx['T'][end($sequence)][$endIdx] = (int) $legs[count($legs) - 1]['duration_min'];
                }
            }
        };
        $applyLegs($legs);
        $sim = $this->simulate($sequence, $ctx, $lenient);
        $transitUsed = false;
        if ($transit && $this->transit->enabled() && count($legs) === count($routePoints) - 1) {
            // Chaque tronçon de plus de 12 min à pied est comparé au meilleur trajet en transports à l'heure réelle de départ.
            foreach ($legs as $i => $leg) {
                if ((int) $leg['duration_min'] <= 12) {
                    continue;
                }
                $departMin = $i === 0 ? $startMin : ($sim['steps'][$i - 1]['leave'] ?? $startMin);
                $journey = $this->transit->journey($routePoints[$i], $routePoints[$i + 1], $date->copy()->addMinutes($departMin));
                if ($journey && $journey['duration_min'] + 3 < (int) $leg['duration_min']) {
                    $legs[$i] = ['distance_km' => $journey['distance_km'], 'duration_min' => $journey['duration_min'], 'shape' => $journey['shape'], 'maneuvers' => $journey['maneuvers'], 'transit' => true, 'sections' => $journey['sections'], 'lines' => $journey['lines'], 'summary' => $journey['summary'], 'walking_min' => $journey['walking_min']];
                    $transitUsed = true;
                }
            }
            if ($transitUsed) {
                $route['legs'] = $legs;
                $route['geometry'] = array_merge(...array_map(fn ($l) => $l['shape'] ?? [], $legs));
                $route['distance_km'] = round(array_sum(array_map(fn ($l) => (float) $l['distance_km'], $legs)), 2);
                $applyLegs($legs);
                $sim = $this->simulate($sequence, $ctx, $lenient);
            }
        }
        $unusedIndoor = array_values(array_filter(array_keys($rows), fn ($i) => ! in_array($i + 1, $sequence, true) && in_array($rows[$i]['slug'], self::INDOOR, true) && $rows[$i]['slug'] !== 'restauration'));

        $steps = [];
        $totalCost = 0.0;
        foreach ($sequence as $i => $idx) {
            $node = $nodes[$idx - 1];
            $place = $node['place'];
            $s = $sim['steps'][$i];
            $prev = $i === 0 ? 0 : $sequence[$i - 1];
            $leg = $legs[$i] ?? ['distance_km' => $K[$prev][$idx] ?? 0, 'duration_min' => $T[$prev][$idx] ?? 0];
            $totalCost += $node['cost'];
            $arrive = $date->copy()->addMinutes($s['arrive']);
            $alternative = null;
            if ($node['kind'] === 'visit' && $weather && $weather['indoor_recommended'] && in_array($node['slug'], self::OUTDOOR, true) && $unusedIndoor !== []) {
                usort($unusedIndoor, fn ($a, $b) => ($T[$idx][$a + 1] ?? 999) <=> ($T[$idx][$b + 1] ?? 999));
                $alt = $rows[$unusedIndoor[0]]['place'];
                $alternative = ['place_id' => $alt->id, 'title' => $alt->title, 'category' => $alt->category->name ?? null, 'cover' => $alt->coverThumb(330), 'minutes_away' => $T[$idx][$unusedIndoor[0] + 1] ?? null];
                array_shift($unusedIndoor);
            }
            $steps[] = [
                'order' => $i + 1,
                'kind' => $node['kind'],
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
                'cost_eur' => round($node['cost'], 2),
                'visit_minutes' => $node['visit'],
                'short_visit' => ! empty($node['short']),
                'travel_minutes' => (int) $leg['duration_min'],
                'travel_km' => round((float) $leg['distance_km'], 2),
                'travel_mode' => ! empty($leg['transit']) ? 'transit' : $mode,
                'transit' => ! empty($leg['transit']) ? ['summary' => $leg['summary'], 'lines' => $leg['lines'], 'sections' => $leg['sections'], 'walking_min' => $leg['walking_min']] : null,
                'wait_minutes' => $s['wait'],
                'conflict' => $s['conflict'] ?? false,
                'accessible' => $place->accessible,
                'accessibility_note' => $place->accessibility_note,
                'locked' => $node['required'] ?? false,
                'arrive_at' => $arrive->format('H:i'),
                'start_visit_at' => $date->copy()->addMinutes($s['begin'])->format('H:i'),
                'leave_at' => $date->copy()->addMinutes($s['leave'])->format('H:i'),
                'hours' => [
                    'status' => $node['hours']['status'],
                    'opens' => $node['hours']['opens'] !== null ? $this->hhmm($node['hours']['opens']) : null,
                    'closes' => $node['hours']['closes'] !== null ? $this->hhmm($node['hours']['closes']) : null,
                    'note' => $node['hours']['note'],
                ],
                'alternative' => $alternative,
                'reason' => $node['kind'] === 'lunch' ? __('Pause déjeuner sur le chemin') : (! empty($node['short']) ? __('Visite express pour tenir dans le temps') : $this->reason($place, $interests, $weather, $node['hours'])),
            ];
        }

        $finalLeg = null;
        if ($endIdx !== null) {
            $lastLeg = $legs[count($sequence)] ?? ['distance_km' => $K[end($sequence)][$endIdx] ?? 0, 'duration_min' => $T[end($sequence)][$endIdx] ?? 0];
            $finalLeg = ['travel_minutes' => (int) $lastLeg['duration_min'], 'travel_km' => round((float) $lastLeg['distance_km'], 2), 'arrive_at' => $date->copy()->addMinutes($sim['end'])->format('H:i'), 'travel_mode' => ! empty($lastLeg['transit']) ? 'transit' : $mode, 'transit' => ! empty($lastLeg['transit']) ? ['summary' => $lastLeg['summary'], 'lines' => $lastLeg['lines'], 'sections' => $lastLeg['sections'], 'walking_min' => $lastLeg['walking_min']] : null];
        }

        $warnings = [];
        if ($skippedBudget > 0) {
            $warnings[] = __(':n lieu(x) écarté(s) pour rester dans le budget.', ['n' => $skippedBudget]);
        }
        if ($closedCount > 0) {
            $warnings[] = __(':n lieu(x) fermé(s) à cette date ont été écartés.', ['n' => $closedCount]);
        }
        if ($trimmedForOrder) {
            $warnings[] = __('Ta sélection a été raccourcie pour tenir dans le temps disponible.');
        }
        if ($transit && ! $this->transit->enabled()) {
            $warnings[] = __('Transports en commun non configurés sur ce serveur : trajets calculés à pied.');
        }
        if (($route['source'] ?? '') === 'estimate' || ($matrix['source'] ?? '') === 'estimate') {
            $warnings[] = __('Service de routage indisponible : durées estimées à vol d\'oiseau.');
        }
        if ($weather && $weather['indoor_recommended']) {
            $warnings[] = __('Météo : :label (:rain % de pluie). Les lieux couverts sont privilégiés et chaque étape en extérieur a un plan B.', ['label' => $weather['label'], 'rain' => $weather['rain_probability']]);
        }
        if ($lenient && $sim['total'] > $timeBudget) {
            $warnings[] = __('Ce parcours dépasse ton temps disponible de :n min.', ['n' => $sim['total'] - $timeBudget]);
        }
        foreach ($steps as $st) {
            if ($st['conflict']) {
                $warnings[] = __(':title : la visite finirait après la fermeture (:closes).', ['title' => $st['title'], 'closes' => $st['hours']['closes']]);
            }
        }
        if ($accessible) {
            $unknownAccess = count(array_filter($steps, fn ($s) => $s['accessible'] === null && $s['kind'] === 'visit'));
            if ($unknownAccess > 0) {
                $warnings[] = __(':n étape(s) sans information d\'accessibilité vérifiée : le trajet évite les escaliers, mais vérifie l\'entrée.', ['n' => $unknownAccess]);
            }
        }
        $unknown = count(array_filter($steps, fn ($s) => $s['hours']['status'] === 'unknown'));
        if ($unknown > 0) {
            $warnings[] = __(':n étape(s) sans horaires connus : vérifie avant de partir.', ['n' => $unknown]);
        }

        $this->accessibleFlag = $accessible;
        $out = $this->result($start, $end, $loop, $startsAt, $travelMode, $steps, $route, $weather, $warnings, round($totalCost, 2), $sim, $finalLeg, $matrix['source'] ?? 'estimate');
        $out['transit_used'] = $transitUsed;
        $out['shortlist_ids'] = array_map(fn ($r) => (int) $r['place']->id, $rows);

        return $out;
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

    // ------------------------------------------------------------------------------------------ planification

    /**
     * Insertion au moindre détour : à chaque tour, le candidat (et la position) qui apporte le meilleur
     * score par minute de détour tout en restant faisable (temps, fenêtres horaires, budget, diversité).
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array{0:array<int,int>,1:int,2:array<int,array<string,mixed>>}
     */
    private function plan(array $rows, array $ctx, ?float $budgetEur, int $maxSteps, array $sequence = []): array
    {
        $used = [];
        $perCategory = [];
        $spent = 0.0;
        // Reprise d'une séquence existante (après insertion du déjeuner) : on repart de son état.
        foreach ($sequence as $idx) {
            $used[$idx] = true;
            if (isset($rows[$idx - 1])) {
                $perCategory[$rows[$idx - 1]['slug']] = ($perCategory[$rows[$idx - 1]['slug']] ?? 0) + 1;
                $spent += $rows[$idx - 1]['cost'];
            }
        }
        $skippedBudget = 0;
        // Lieux imposés (verrouillés) : insérés d'abord, à la position de moindre détour.
        foreach ($ctx['required'] ?? [] as $idx) {
            if (isset($used[$idx])) {
                continue;
            }
            $bestSeq = null;
            $bestTotal = null;
            for ($pos = 0; $pos <= count($sequence); $pos++) {
                $candidate = $sequence;
                array_splice($candidate, $pos, 0, [$idx]);
                $sim = $this->simulate($candidate, $ctx);
                if ($sim['feasible'] && ($bestTotal === null || $sim['total'] < $bestTotal)) {
                    $bestSeq = $candidate;
                    $bestTotal = $sim['total'];
                }
            }
            if ($bestSeq !== null) {
                $sequence = $bestSeq;
                $used[$idx] = true;
                $perCategory[$rows[$idx - 1]['slug']] = ($perCategory[$rows[$idx - 1]['slug']] ?? 0) + 1;
                $spent += $rows[$idx - 1]['cost'];
            }
        }
        $base = $this->simulate($sequence, $ctx);
        $factor = 1.0;

        while (count($sequence) < $maxSteps) {
            $best = null;
            foreach ($rows as $i => $row) {
                $idx = $i + 1;
                if (isset($used[$idx])) {
                    continue;
                }
                if ($factor < 1.0) {
                    // Seconde passe : visite raccourcie pour remplir le temps restant.
                    $ctx['nodes'][$i]['visit'] = max(30, (int) round($row['visit'] * $factor));
                    $ctx['nodes'][$i]['short'] = true;
                }
                $cap = self::MAX_PER_CATEGORY[$row['slug']] ?? self::MAX_PER_CATEGORY['default'];
                if (($perCategory[$row['slug']] ?? 0) >= $cap || $row['score'] < -5) {
                    continue;
                }
                if ($budgetEur !== null && $spent + $row['cost'] > $budgetEur) {
                    $skippedBudget++;
                    $used[$idx] = true;

                    continue;
                }
                for ($pos = 0; $pos <= count($sequence); $pos++) {
                    $candidate = $sequence;
                    array_splice($candidate, $pos, 0, [$idx]);
                    $sim = $this->simulate($candidate, $ctx);
                    if (! $sim['feasible']) {
                        continue;
                    }
                    $detour = max(1, $sim['travel'] - $base['travel']);
                    $value = ($row['score'] + 3.0) / (1 + $detour / 12) - $sim['wait'] * 0.02;
                    if ($best === null || $value > $best['value']) {
                        $best = ['value' => $value, 'seq' => $candidate, 'idx' => $idx, 'row' => $row, 'sim' => $sim];
                    }
                }
            }
            if ($best === null) {
                if ($factor === 1.0) {
                    $factor = 0.7;

                    continue;
                }
                break;
            }
            $sequence = $best['seq'];
            $used[$best['idx']] = true;
            $perCategory[$best['row']['slug']] = ($perCategory[$best['row']['slug']] ?? 0) + 1;
            $spent += $best['row']['cost'];
            $base = $best['sim'];
        }

        // Les visites non retenues retrouvent leur durée normale.
        foreach ($ctx['nodes'] as $i => &$node) {
            if (! in_array($i + 1, $sequence, true) && ! empty($node['short'])) {
                $node['visit'] = $rows[$i]['visit'] ?? $node['visit'];
                $node['short'] = false;
            }
        }
        unset($node);

        return [$sequence, $skippedBudget, $ctx['nodes']];
    }

    /** Amélioration locale : inversion de segments tant que le temps total baisse et que tout reste faisable. */
    private function twoOpt(array $sequence, array $ctx): array
    {
        $n = count($sequence);
        if ($n < 3) {
            return $sequence;
        }
        $best = $this->simulate($sequence, $ctx);
        $improved = true;
        $guard = 0;
        while ($improved && $guard++ < 50) {
            $improved = false;
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $candidate = $sequence;
                    $segment = array_reverse(array_slice($candidate, $i, $j - $i + 1));
                    array_splice($candidate, $i, $j - $i + 1, $segment);
                    $sim = $this->simulate($candidate, $ctx);
                    if ($sim['feasible'] && $sim['total'] < $best['total'] - 1) {
                        $sequence = $candidate;
                        $best = $sim;
                        $improved = true;
                    }
                }
            }
        }

        return $sequence;
    }

    /** Insère un restaurant à la position où l'on passe le plus près de 12 h 30, si le parcours couvre le créneau. */
    private function insertLunch(array $sequence, array $ctx, int $visitCount, ?float $budgetEur): array
    {
        $sim = $this->simulate($sequence, $ctx);
        $lunchTarget = 12 * 60 + 30;
        if ($ctx['startMin'] > 14 * 60 || $sim['end'] < 12 * 60) {
            return $sequence;
        }
        $bestSeq = null;
        $bestDelta = null;
        $n = count($ctx['nodes']);
        for ($idx = $visitCount + 1; $idx <= $n; $idx++) {
            for ($pos = 0; $pos <= count($sequence); $pos++) {
                $candidate = $sequence;
                array_splice($candidate, $pos, 0, [$idx]);
                $s = $this->simulate($candidate, $ctx);
                if (! $s['feasible']) {
                    continue;
                }
                $arrive = $s['steps'][$pos]['arrive'];
                if ($arrive < 11 * 60 + 30 || $arrive > 14 * 60 + 15) {
                    continue;
                }
                $delta = abs($arrive - $lunchTarget) + max(0, $s['travel'] - $sim['travel']);
                if ($bestDelta === null || $delta < $bestDelta) {
                    $bestDelta = $delta;
                    $bestSeq = $candidate;
                }
            }
        }
        if ($bestSeq === null && count($sequence) > 2) {
            // Pas de place : on sacrifie la dernière visite pour caser la pause.
            $shorter = $sequence;
            array_pop($shorter);

            return $this->insertLunch($shorter, $ctx, $visitCount, $budgetEur);
        }

        return $bestSeq ?? $sequence;
    }

    /**
     * Simule l'enchaînement : trajets (matrice), attente devant un lieu fermé, visite, retour éventuel.
     *
     * @param array<int,int> $sequence indices dans la matrice (1..n)
     * @return array{feasible:bool,total:int,travel:int,wait:int,end:int,steps:array<int,array{arrive:int,begin:int,leave:int,wait:int}>}
     */
    private function simulate(array $sequence, array $ctx, bool $lenient = false): array
    {
        $T = $ctx['T'];
        $t = $ctx['startMin'];
        $travel = 0;
        $wait = 0;
        $steps = [];
        $prev = 0;
        foreach ($sequence as $idx) {
            $node = $ctx['nodes'][$idx - 1];
            $leg = (int) ($T[$prev][$idx] ?? 0);
            $t += $leg;
            $travel += $leg;
            $arrive = $t;
            $w = 0;
            $conflict = false;
            $h = $node['hours'];
            if ($h['status'] === 'open') {
                if ($t < $h['opens']) {
                    $w = $h['opens'] - $t;
                    if ($w > self::MAX_WAIT_MIN && ! $lenient) {
                        return ['feasible' => false, 'total' => PHP_INT_MAX, 'travel' => $travel, 'wait' => $wait, 'end' => $t, 'steps' => []];
                    }
                    $t = $h['opens'];
                }
                if ($h['closes'] !== null && $t + $node['visit'] > $h['closes'] + 5) {
                    if (! $lenient) {
                        return ['feasible' => false, 'total' => PHP_INT_MAX, 'travel' => $travel, 'wait' => $wait, 'end' => $t, 'steps' => []];
                    }
                    $conflict = true;
                }
            }
            $wait += $w;
            $begin = $t;
            $t += $node['visit'];
            $steps[] = ['arrive' => $arrive, 'begin' => $begin, 'leave' => $t, 'wait' => $w, 'conflict' => $conflict];
            $prev = $idx;
        }
        if ($ctx['endIdx'] !== null && $sequence !== []) {
            $leg = (int) ($T[$prev][$ctx['endIdx']] ?? 0);
            $t += $leg;
            $travel += $leg;
        }
        $total = $t - $ctx['startMin'];
        $feasible = $total <= $ctx['budget'] && ($sequence === [] || $travel <= max(20, (int) round($ctx['budget'] * self::MAX_TRAVEL_SHARE)));

        return ['feasible' => $feasible, 'total' => $total, 'travel' => $travel, 'wait' => $wait, 'end' => $t, 'steps' => $steps];
    }

    // ------------------------------------------------------------------------------------------ résultat

    private function reason(Place $place, array $interests, ?array $weather, array $hours): string
    {
        $slug = $place->category->slug ?? '';
        if ($interests !== [] && in_array($slug, $interests, true)) {
            return __('Correspond à tes centres d\'intérêt');
        }
        if ($weather && $weather['indoor_recommended'] && in_array($slug, self::INDOOR, true)) {
            return __('À l\'abri en cas de pluie');
        }
        if ($place->event_start_at || $place->event_end_at) {
            return __('Événement en cours à cette date');
        }
        if ($place->is_free) {
            return 'Gratuit';
        }
        if ($place->reviews_avg_rating) {
            return __('Bien noté par la communauté');
        }
        if ($hours['status'] === 'open') {
            return __('Ouvert à ton passage');
        }

        return __('Proche de ton parcours');
    }

    private function result(array $start, ?array $end, bool $loop, Carbon $startsAt, string $mode, array $steps, array $route, ?array $weather, array $warnings, float $totalCost = 0.0, ?array $sim = null, ?array $finalLeg = null, string $matrixSource = 'none'): array
    {
        $travel = array_sum(array_map(fn ($s) => $s['travel_minutes'], $steps)) + ($finalLeg['travel_minutes'] ?? 0);
        $visit = array_sum(array_map(fn ($s) => $s['visit_minutes'], $steps));
        $wait = array_sum(array_map(fn ($s) => $s['wait_minutes'] ?? 0, $steps));
        $totalMinutes = $travel + $visit + $wait;
        $categories = array_values(array_unique(array_filter(array_map(fn ($s) => $s['kind'] === 'lunch' ? null : $s['category'], $steps))));
        $distance = (float) ($route['distance_km'] ?? 0);

        return [
            'version' => 3,
            'planner' => 'insertion+2opt',
            'title' => $this->title($categories, $start['label'] ?? null),
            'mode' => $mode,
            'accessible' => $this->accessibleFlag,
            'start' => ['lat' => (float) $start['lat'], 'lng' => (float) $start['lng'], 'label' => $start['label'] ?? __('Point de départ')],
            'end' => $end ? ['lat' => (float) $end['lat'], 'lng' => (float) $end['lng'], 'label' => $end['label'] ?? __('Arrivée')] + ($finalLeg ?? []) : null,
            'loop' => $loop,
            'date' => $startsAt->format('Y-m-d'),
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $startsAt->copy()->addMinutes($totalMinutes)->toIso8601String(),
            'total_minutes' => $totalMinutes,
            'travel_minutes' => $travel,
            'visit_minutes' => $visit,
            'wait_minutes' => $wait,
            'travel_share' => $totalMinutes > 0 ? (int) round($travel / $totalMinutes * 100) : 0,
            'total_distance_km' => round($distance, 2),
            'total_cost_eur' => $totalCost,
            'steps' => $steps,
            'geometry' => $route['geometry'] ?? [],
            'legs' => array_map(fn ($l) => ['distance_km' => $l['distance_km'], 'duration_min' => $l['duration_min'], 'shape' => $l['shape'] ?? [], 'maneuvers' => $l['maneuvers'] ?? [], 'transit' => ! empty($l['transit']), 'sections' => $l['sections'] ?? [], 'lines' => $l['lines'] ?? [], 'summary' => $l['summary'] ?? null], $route['legs'] ?? []),
            'routing_source' => $route['source'] ?? 'none',
            'matrix_source' => $matrixSource,
            'weather' => $weather,
            'warnings' => $warnings,
        ];
    }

    private function title(array $categories, ?string $startLabel): string
    {
        if ($categories === []) {
            return __('Parcours CAMINO');
        }
        $main = array_slice($categories, 0, 2);
        $label = implode(' & ', array_map(fn ($c) => mb_strtolower($c), $main));
        $from = $startLabel && ! in_array($startLabel, ['Point de départ', 'Ma position', __('Point de départ'), __('Ma position')], true) ? ' · ' . mb_substr($startLabel, 0, 40) : '';

        return __('Balade :label', ['label' => $label]) . $from;
    }

    private function hhmm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
