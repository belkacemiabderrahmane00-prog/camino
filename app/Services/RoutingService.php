<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Routage réel via Valhalla (OpenStreetMap) : trajets piéton / vélo sur le réseau de rues,
 * avec optimisation de l'ordre des étapes (problème du voyageur de commerce).
 *
 * Chaque point est un tableau ['lat' => float, 'lng' => float]. En cas d'indisponibilité
 * du service, on retombe sur une estimation à vol d'oiseau (distance haversine × 1.3).
 */
class RoutingService
{
    public const MODE_WALK = 'walk';

    public const MODE_BIKE = 'bike';

    private const COSTING = [
        self::MODE_WALK => 'pedestrian',
        self::MODE_BIKE => 'bicycle',
    ];

    /**
     * Trajet dans l'ordre donné.
     *
     * @param array<int,array{lat:float,lng:float}> $points
     * @return array{distance_km:float,duration_min:int,legs:array<int,array{distance_km:float,duration_min:int}>,geometry:array<int,array{0:float,1:float}>,source:string}
     */
    public function route(array $points, string $mode = self::MODE_WALK): array
    {
        if (count($points) < 2) {
            return $this->empty();
        }

        $result = $this->call('/route', $points, $mode);

        return $result ?? $this->fallback($points, $mode);
    }

    /**
     * Trajet avec ordre optimisé : le premier point reste le départ, les autres sont réordonnés.
     * Retourne en plus `order` : indices d'origine dans l'ordre de visite (départ inclus en position 0).
     *
     * @param array<int,array{lat:float,lng:float}> $points
     * @return array{distance_km:float,duration_min:int,legs:array,geometry:array,order:array<int,int>,source:string}
     */
    public function optimizedRoute(array $points, string $mode = self::MODE_WALK): array
    {
        if (count($points) < 3) {
            $r = $this->route($points, $mode);
            $r['order'] = array_keys($points);

            return $r;
        }

        $result = $this->call('/optimized_route', $points, $mode);
        if ($result === null) {
            $r = $this->fallback($points, $mode);
            $r['order'] = array_keys($points);

            return $r;
        }

        return $result;
    }

    /**
     * Matrice temps/distance entre tous les points (Valhalla sources_to_targets), avec repli haversine.
     *
     * @param array<int,array{lat:float,lng:float}> $points
     * @return array{minutes:array<int,array<int,int>>, km:array<int,array<int,float>>, source:string}
     */
    public function matrix(array $points, string $mode = self::MODE_WALK): array
    {
        $points = array_values($points);
        $n = count($points);
        $costing = self::COSTING[$mode] ?? 'pedestrian';
        $locations = array_map(fn (array $p) => ['lat' => round((float) $p['lat'], 6), 'lon' => round((float) $p['lng'], 6)], $points);
        $cacheKey = 'routing:matrix:' . md5($costing . json_encode($locations));

        // L'instance publique limite chaque appel à 100 paires : on découpe en blocs 10 × 10 envoyés en parallèle.
        // L'instance publique limite chaque appel à 100 paires source × cible et n'aime pas les rafales :
        // on envoie des blocs « quelques sources × toutes les cibles », par vagues de 3 requêtes.
        $result = $n >= 2 && $n <= 60 ? Cache::remember($cacheKey, now()->addMinutes(config('camino.routing.cache_minutes', 10080)), function () use ($locations, $costing, $n) {
            $perRequest = max(1, intdiv(100, $n));
            $groups = array_chunk(range(0, $n - 1), $perRequest);
            $base = rtrim(config('camino.routing.base_url'), '/') . '/sources_to_targets';
            $timeout = config('camino.routing.timeout', 20);
            $minutes = [];
            $km = [];
            foreach (array_chunk($groups, 3, true) as $wave) {
                try {
                    $responses = Http::pool(function ($pool) use ($wave, $locations, $costing, $base, $timeout) {
                        foreach ($wave as $key => $sources) {
                            $pool->as((string) $key)->timeout($timeout)->withHeaders(['User-Agent' => config('camino.user_agent')])
                                ->post($base, ['sources' => array_map(fn ($i) => $locations[$i], $sources), 'targets' => $locations, 'costing' => $costing, 'units' => 'kilometers']);
                        }
                    });
                } catch (\Throwable $e) {
                    Log::warning('Routing matrix unavailable: ' . $e->getMessage());

                    return null;
                }
                foreach ($wave as $key => $sources) {
                    $response = $responses[(string) $key] ?? null;
                    if (! $response instanceof \Illuminate\Http\Client\Response || ! $response->ok()) {
                        // Une seconde chance, seule et après une courte pause.
                        usleep(400000);
                        try {
                            $response = Http::timeout($timeout)->withHeaders(['User-Agent' => config('camino.user_agent')])
                                ->post($base, ['sources' => array_map(fn ($i) => $locations[$i], $sources), 'targets' => $locations, 'costing' => $costing, 'units' => 'kilometers']);
                        } catch (\Throwable $e) {
                            Log::warning('Routing matrix unavailable: ' . $e->getMessage() . " ($n points)");

                            return null;
                        }
                        if (! $response->ok()) {
                            Log::warning('Routing matrix error: HTTP ' . $response->status() . ' ' . substr($response->body(), 0, 160) . " ($n points)");

                            return null;
                        }
                    }
                    $rows = $response->json('sources_to_targets');
                    if (! is_array($rows) || count($rows) !== count($sources)) {
                        return null;
                    }
                    foreach ($rows as $r => $row) {
                        foreach ($row as $c => $cell) {
                            $t = $cell['time'] ?? null;
                            $minutes[$sources[$r]][$c] = $t === null ? null : (int) ceil($t / 60);
                            $km[$sources[$r]][$c] = round((float) ($cell['distance'] ?? 0), 2);
                        }
                    }
                }
            }

            return ['minutes' => $minutes, 'km' => $km, 'source' => 'valhalla'];
        }) : null;

        // Cases inaccessibles ou service indisponible : estimation à vol d'oiseau.
        $minutes = $result['minutes'] ?? [];
        $km = $result['km'] ?? [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (! isset($minutes[$i][$j])) {
                    $minutes[$i][$j] = $i === $j ? 0 : $this->estimateMinutes($points[$i]['lat'], $points[$i]['lng'], $points[$j]['lat'], $points[$j]['lng'], $mode);
                    $km[$i][$j] = $i === $j ? 0.0 : round($this->haversineKm($points[$i]['lat'], $points[$i]['lng'], $points[$j]['lat'], $points[$j]['lng']) * 1.3, 2);
                }
            }
        }

        return ['minutes' => $minutes, 'km' => $km, 'source' => $result['source'] ?? 'estimate'];
    }

    /**
     * Estimation rapide (sans appel réseau) de la durée entre deux points.
     */
    public function estimateMinutes(float $lat1, float $lng1, float $lat2, float $lng2, string $mode = self::MODE_WALK): int
    {
        $km = $this->haversineKm($lat1, $lng1, $lat2, $lng2) * 1.3;
        $speed = config('camino.fallback_speed_kmh.' . $mode, 4.0);

        return (int) ceil($km / $speed * 60);
    }

    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param array<int,array{lat:float,lng:float}> $points
     */
    private function call(string $endpoint, array $points, string $mode): ?array
    {
        $costing = self::COSTING[$mode] ?? 'pedestrian';
        $locations = array_map(fn (array $p) => ['lat' => round((float) $p['lat'], 6), 'lon' => round((float) $p['lng'], 6)], array_values($points));
        $cacheKey = 'routing:' . md5($endpoint . $costing . json_encode($locations));

        return Cache::remember($cacheKey, now()->addMinutes(config('camino.routing.cache_minutes', 10080)), function () use ($endpoint, $locations, $costing) {
            try {
                $response = Http::timeout(config('camino.routing.timeout', 20))
                    ->withHeaders(['User-Agent' => config('camino.user_agent')])
                    ->post(rtrim(config('camino.routing.base_url'), '/') . $endpoint, [
                        'locations' => $locations,
                        'costing' => $costing,
                        'units' => 'kilometers',
                        'directions_options' => ['language' => 'fr-FR'],
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Routing unavailable: ' . $e->getMessage());

                return null;
            }

            if (! $response->ok() || ! isset($response['trip'])) {
                Log::warning('Routing error: HTTP ' . $response->status() . ' ' . substr($response->body(), 0, 200));

                return null;
            }

            return $this->parseTrip($response['trip']);
        });
    }

    private function parseTrip(array $trip): array
    {
        $legs = [];
        $geometry = [];
        foreach ($trip['legs'] ?? [] as $leg) {
            $shape = $this->decodePolyline((string) ($leg['shape'] ?? ''));
            $maneuvers = [];
            foreach ($leg['maneuvers'] ?? [] as $m) {
                $maneuvers[] = [
                    'type' => (int) ($m['type'] ?? 8),
                    'text' => (string) ($m['instruction'] ?? ''),
                    'verbal' => (string) ($m['verbal_pre_transition_instruction'] ?? $m['instruction'] ?? ''),
                    'after' => (string) ($m['verbal_post_transition_instruction'] ?? ''),
                    'street' => implode(', ', (array) ($m['street_names'] ?? [])),
                    'km' => round((float) ($m['length'] ?? 0), 3),
                    'sec' => (int) round((float) ($m['time'] ?? 0)),
                    'begin' => (int) ($m['begin_shape_index'] ?? 0),
                    'end' => (int) ($m['end_shape_index'] ?? 0),
                ];
            }
            $legs[] = [
                'distance_km' => round((float) ($leg['summary']['length'] ?? 0), 2),
                'duration_min' => (int) ceil(((float) ($leg['summary']['time'] ?? 0)) / 60),
                'shape' => $shape,
                'maneuvers' => $maneuvers,
            ];
            foreach ($shape as $pt) {
                $geometry[] = $pt;
            }
        }

        return [
            'distance_km' => round((float) ($trip['summary']['length'] ?? 0), 2),
            'duration_min' => (int) ceil(((float) ($trip['summary']['time'] ?? 0)) / 60),
            'legs' => $legs,
            'geometry' => $geometry,
            'order' => array_map(fn (array $l) => (int) ($l['original_index'] ?? 0), $trip['locations'] ?? []),
            'source' => 'valhalla',
        ];
    }

    /**
     * Décodage d'une polyline Google encodée en précision 1e6 (format Valhalla).
     *
     * @return array<int,array{0:float,1:float}> [lat, lng]
     */
    private function decodePolyline(string $encoded, float $precision = 1e6): array
    {
        $points = [];
        $index = 0;
        $lat = 0;
        $lng = 0;
        $len = strlen($encoded);

        while ($index < $len) {
            foreach (['lat', 'lng'] as $coord) {
                $shift = 0;
                $result = 0;
                do {
                    $b = ord($encoded[$index++]) - 63;
                    $result |= ($b & 0x1f) << $shift;
                    $shift += 5;
                } while ($b >= 0x20 && $index < $len);
                $delta = ($result & 1) ? ~($result >> 1) : ($result >> 1);
                if ($coord === 'lat') {
                    $lat += $delta;
                } else {
                    $lng += $delta;
                }
            }
            $points[] = [round($lat / $precision, 6), round($lng / $precision, 6)];
        }

        return $points;
    }

    /**
     * @param array<int,array{lat:float,lng:float}> $points
     */
    private function fallback(array $points, string $mode): array
    {
        $legs = [];
        $geometry = [];
        $totalKm = 0.0;
        $totalMin = 0;
        $prev = null;
        foreach (array_values($points) as $p) {
            $geometry[] = [(float) $p['lat'], (float) $p['lng']];
            if ($prev) {
                $km = round($this->haversineKm($prev['lat'], $prev['lng'], $p['lat'], $p['lng']) * 1.3, 2);
                $min = $this->estimateMinutes($prev['lat'], $prev['lng'], $p['lat'], $p['lng'], $mode);
                $legs[] = ['distance_km' => $km, 'duration_min' => $min, 'shape' => [[(float) $prev['lat'], (float) $prev['lng']], [(float) $p['lat'], (float) $p['lng']]], 'maneuvers' => []];
                $totalKm += $km;
                $totalMin += $min;
            }
            $prev = $p;
        }

        return [
            'distance_km' => round($totalKm, 2),
            'duration_min' => $totalMin,
            'legs' => $legs,
            'geometry' => $geometry,
            'source' => 'estimate',
        ];
    }

    private function empty(): array
    {
        return ['distance_km' => 0.0, 'duration_min' => 0, 'legs' => [], 'geometry' => [], 'source' => 'none'];
    }
}
