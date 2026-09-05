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
    private const INDOOR = ['musee', 'lieu-culturel', 'restauration'];

    private const OUTDOOR = ['parc-jardin', 'street-art', 'itineraire'];

    private const MAX_PER_CATEGORY = ['restauration' => 1, 'default' => 2];

    /** Part maximale du temps passée en trajet. */
    private const MAX_TRAVEL_SHARE = 0.5;

    /** Attente maximale acceptée devant un lieu pas encore ouvert. */
    private const MAX_WAIT_MIN = 25;

    /** Nombre de candidats envoyés à la matrice de temps. */
    private const SHORTLIST = 18;

    public function __construct(
        private readonly RoutingService $routing,
        private readonly WeatherService $weather,
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
        $mode = in_array($options['mode'] ?? null, [RoutingService::MODE_WALK, RoutingService::MODE_BIKE], true) ? $options['mode'] : RoutingService::MODE_WALK;
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

        $start = $options['start'] ?? null;
        if (! $start || ! isset($start['lat'], $start['lng'])) {
            $start = $candidates->isNotEmpty()
                ? ['lat' => (float) $candidates->avg('lat'), 'lng' => (float) $candidates->avg('lng'), 'label' => 'Point de départ']
                : ['lat' => config('camino.default_start.lat'), 'lng' => config('camino.default_start.lng'), 'label' => config('camino.default_start.label')];
        }
        $start['label'] = $start['label'] ?? 'Point de départ';
        $loop = (bool) ($options['loop'] ?? false);
        $end = $options['end'] ?? null;
        if ($loop) {
            $end = ['lat' => (float) $start['lat'], 'lng' => (float) $start['lng'], 'label' => 'Retour au départ'];
        } elseif (! $end || ! isset($end['lat'], $end['lng'])) {
            $end = null;
        } else {
            $end['label'] = $end['label'] ?? 'Arrivée';
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
            $hours = $p->hoursFor($date);
            if ($hours['status'] === 'closed') {
                $closedCount++;

                continue;
            }
            $rows[] = ['place' => $p, 'hours' => $hours, 'slug' => $p->category->slug ?? 'lieu-culturel'];
        }

        if ($rows === []) {
            $why = $closedCount > 0 ? 'Les lieux autour du départ sont fermés à cette date.' : ($freeOnly ? 'Aucun lieu gratuit disponible autour du point de départ.' : 'Aucun lieu disponible autour du point de départ.');

            return $this->result($start, $end, $loop, $startsAt, $mode, [], [], $weather, [$why]);
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
            if (in_array('crowd', $alerts[$p->id] ?? [], true)) {
                $score -= 1.0;
            }
            if ($p->event_start_at || $p->event_end_at) {
                $from = $p->event_start_at?->copy()->startOfDay();
                $to = $p->event_end_at?->copy()->endOfDay() ?? $from?->copy()->endOfDay();
                $score += ($from === null || $date->gte($from)) && ($to === null || $date->lte($to)) ? 1.2 : -3.0;
            }
            $distanceKm = $this->routing->haversineKm((float) $start['lat'], (float) $start['lng'], (float) $p->lat, (float) $p->lng);
            $score -= $distanceKm * ($mode === RoutingService::MODE_BIKE ? 0.15 : 0.45);
            $row['score'] = $score;
            $row['distance_km'] = $distanceKm;
            $row['visit'] = (int) ($p->visit_duration_min ?: 60);
            $row['cost'] = $this->estimateCost($p);
            $row['kind'] = 'visit';
        }
        unset($row);

        // Liste courte pour la matrice de temps (le calcul TSP se fait dessus).
        if (! $preserveOrder) {
            usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);
            $rows = array_slice($rows, 0, self::SHORTLIST);
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
        $matrix = $this->routing->matrix($points, $mode);
        $T = $matrix['minutes'];
        $K = $matrix['km'];

        $ctx = ['T' => $T, 'nodes' => $nodes, 'startMin' => $startMin, 'budget' => $timeBudget, 'endIdx' => $endIdx];

        // --- 4. Planification -----------------------------------------------------------------------
        $skippedBudget = 0;
        $trimmedForOrder = false;
        if ($preserveOrder) {
            $sequence = range(1, count($rows));
            while ($sequence !== [] && ! $this->simulate($sequence, $ctx)['feasible']) {
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
            $why = $skippedBudget > 0 ? 'Le budget indiqué est trop faible pour les lieux disponibles.' : 'Le temps disponible est trop court pour proposer un parcours avec ces lieux.';
            if ($closingSoon > 0 && $closingSoon >= count($rows) * 0.6) {
                $why = 'À cette heure, les lieux autour du départ sont fermés ou ferment bientôt. Choisis un autre créneau ou une autre date.';
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
        $route = $this->routing->route($routePoints, $mode);
        $legs = $route['legs'] ?? [];
        // Si le tracé réel diffère de la matrice, on refait les horaires avec ses durées.
        if (count($legs) === count($routePoints) - 1) {
            foreach ($sequence as $i => $idx) {
                $prev = $i === 0 ? 0 : $sequence[$i - 1];
                $ctx['T'][$prev][$idx] = (int) $legs[$i]['duration_min'];
            }
            if ($endIdx !== null) {
                $ctx['T'][end($sequence)][$endIdx] = (int) $legs[count($legs) - 1]['duration_min'];
            }
        }
        $sim = $this->simulate($sequence, $ctx);
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
                'wait_minutes' => $s['wait'],
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
                'reason' => $node['kind'] === 'lunch' ? 'Pause déjeuner sur le chemin' : (! empty($node['short']) ? 'Visite express pour tenir dans le temps' : $this->reason($place, $interests, $weather, $node['hours'])),
            ];
        }

        $finalLeg = null;
        if ($endIdx !== null) {
            $lastLeg = $legs[count($sequence)] ?? ['distance_km' => $K[end($sequence)][$endIdx] ?? 0, 'duration_min' => $T[end($sequence)][$endIdx] ?? 0];
            $finalLeg = ['travel_minutes' => (int) $lastLeg['duration_min'], 'travel_km' => round((float) $lastLeg['distance_km'], 2), 'arrive_at' => $date->copy()->addMinutes($sim['end'])->format('H:i')];
        }

        $warnings = [];
        if ($skippedBudget > 0) {
            $warnings[] = $skippedBudget . ' lieu(x) écarté(s) pour rester dans le budget.';
        }
        if ($closedCount > 0) {
            $warnings[] = $closedCount . ' lieu(x) fermé(s) à cette date ont été écartés.';
        }
        if ($trimmedForOrder) {
            $warnings[] = 'Ta sélection a été raccourcie pour tenir dans le temps disponible.';
        }
        if (($route['source'] ?? '') === 'estimate' || ($matrix['source'] ?? '') === 'estimate') {
            $warnings[] = 'Service de routage indisponible : durées estimées à vol d\'oiseau.';
        }
        if ($weather && $weather['indoor_recommended']) {
            $warnings[] = sprintf('Météo : %s (%d %% de pluie). Les lieux couverts sont privilégiés et chaque étape en extérieur a un plan B.', $weather['label'], $weather['rain_probability']);
        }
        $unknown = count(array_filter($steps, fn ($s) => $s['hours']['status'] === 'unknown'));
        if ($unknown > 0) {
            $warnings[] = $unknown . ' étape(s) sans horaires connus : vérifie avant de partir.';
        }

        return $this->result($start, $end, $loop, $startsAt, $mode, $steps, $route, $weather, $warnings, round($totalCost, 2), $sim, $finalLeg, $matrix['source'] ?? 'estimate');
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
                if (($perCategory[$row['slug']] ?? 0) >= $cap) {
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
    private function simulate(array $sequence, array $ctx): array
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
            $h = $node['hours'];
            if ($h['status'] === 'open') {
                if ($t < $h['opens']) {
                    $w = $h['opens'] - $t;
                    if ($w > self::MAX_WAIT_MIN) {
                        return ['feasible' => false, 'total' => PHP_INT_MAX, 'travel' => $travel, 'wait' => $wait, 'end' => $t, 'steps' => []];
                    }
                    $t = $h['opens'];
                }
                if ($h['closes'] !== null && $t + $node['visit'] > $h['closes'] + 5) {
                    return ['feasible' => false, 'total' => PHP_INT_MAX, 'travel' => $travel, 'wait' => $wait, 'end' => $t, 'steps' => []];
                }
            }
            $wait += $w;
            $begin = $t;
            $t += $node['visit'];
            $steps[] = ['arrive' => $arrive, 'begin' => $begin, 'leave' => $t, 'wait' => $w];
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
            return 'Correspond à tes centres d\'intérêt';
        }
        if ($weather && $weather['indoor_recommended'] && in_array($slug, self::INDOOR, true)) {
            return 'À l\'abri en cas de pluie';
        }
        if ($place->event_start_at || $place->event_end_at) {
            return 'Événement en cours à cette date';
        }
        if ($place->is_free) {
            return 'Gratuit';
        }
        if ($place->reviews_avg_rating) {
            return 'Bien noté par la communauté';
        }
        if ($hours['status'] === 'open') {
            return 'Ouvert à ton passage';
        }

        return 'Proche de ton parcours';
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
            'start' => ['lat' => (float) $start['lat'], 'lng' => (float) $start['lng'], 'label' => $start['label'] ?? 'Point de départ'],
            'end' => $end ? ['lat' => (float) $end['lat'], 'lng' => (float) $end['lng'], 'label' => $end['label'] ?? 'Arrivée'] + ($finalLeg ?? []) : null,
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
            'legs' => array_map(fn ($l) => ['distance_km' => $l['distance_km'], 'duration_min' => $l['duration_min'], 'shape' => $l['shape'] ?? [], 'maneuvers' => $l['maneuvers'] ?? []], $route['legs'] ?? []),
            'routing_source' => $route['source'] ?? 'none',
            'matrix_source' => $matrixSource,
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
        $from = $startLabel && ! in_array($startLabel, ['Point de départ', 'Ma position'], true) ? ' · ' . mb_substr($startLabel, 0, 40) : '';

        return 'Balade ' . $label . $from;
    }

    private function hhmm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
