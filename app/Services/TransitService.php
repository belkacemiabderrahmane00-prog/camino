<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transports en commun via l'API PRIM d'Île-de-France Mobilités (calculateur Navitia).
 *
 * Un trajet = marche → métro/RER/bus/tram → (correspondance) → marche, avec pour chaque section
 * les horaires, les arrêts, la direction, les alertes trafic, plus le tracé et les consignes vocales.
 * Les autres départs proposés par le calculateur sont conservés comme alternatives.
 */
class TransitService
{
    public function enabled(): bool
    {
        return (string) config('camino.transit.api_key') !== '';
    }

    /**
     * Meilleur trajet en transports entre deux points à une heure donnée (null si aucun ou indisponible).
     *
     * @param array{lat:float,lng:float} $from
     * @param array{lat:float,lng:float} $to
     */
    public function journey(array $from, array $to, Carbon $at): ?array
    {
        $journeys = $this->journeys($from, $to, $at);

        return $journeys[0] ?? null;
    }

    /**
     * Trajets en transports (le meilleur d'abord, avec ses alternatives), ou [] si aucun.
     *
     * @param array{lat:float,lng:float} $from
     * @param array{lat:float,lng:float} $to
     * @return array<int,array<string,mixed>>
     */
    public function journeys(array $from, array $to, Carbon $at, bool $realtime = false): array
    {
        if (! $this->enabled()) {
            return [];
        }
        // Les horaires changent peu à l'échelle d'un quart d'heure : cache par créneau (sauf temps réel : 2 min).
        $slot = $realtime ? $at->copy()->second(0) : $at->copy()->minute(intdiv($at->minute, 15) * 15)->second(0);
        $key = 'transit:v2:' . md5(round($from['lat'], 4) . ',' . round($from['lng'], 4) . '|' . round($to['lat'], 4) . ',' . round($to['lng'], 4) . '|' . $slot->format('YmdHi') . '|' . ($realtime ? 'rt' : 'base')) . ':' . app()->getLocale();
        $ttl = $realtime ? now()->addMinutes(2) : now()->addMinutes((int) config('camino.transit.cache_minutes', 60));

        return Cache::remember($key, $ttl, function () use ($from, $to, $slot, $realtime) {
            try {
                $response = Http::timeout((int) config('camino.transit.timeout', 10))
                    ->withHeaders(['apiKey' => config('camino.transit.api_key'), 'Accept' => 'application/json', 'Accept-Encoding' => 'gzip', 'User-Agent' => config('camino.user_agent')])
                    ->get(rtrim(config('camino.transit.base_url'), '/') . '/journeys', [
                        'from' => round($from['lng'], 6) . ';' . round($from['lat'], 6),
                        'to' => round($to['lng'], 6) . ';' . round($to['lat'], 6),
                        'datetime' => $slot->format('Ymd\THis'),
                        'max_nb_journeys' => 4,
                        'max_walking_duration_to_pt' => 900,
                        'data_freshness' => $realtime ? 'realtime' : 'base_schedule',
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Transit unavailable: ' . $e->getMessage());

                return [];
            }
            if (! $response->ok()) {
                Log::warning('Transit error: HTTP ' . $response->status() . ' ' . substr($response->body(), 0, 160));

                return [];
            }
            $disruptions = $this->disruptions($response->json('disruptions', []) ?: []);
            $parsed = [];
            foreach ($response->json('journeys', []) as $journey) {
                $hasPt = collect($journey['sections'] ?? [])->contains(fn ($s) => ($s['type'] ?? '') === 'public_transport');
                if (! $hasPt) {
                    continue;
                }
                $parsed[] = $this->parse($journey, $disruptions);
            }
            // Meilleur trajet = celui du calculateur (premier), les autres deviennent ses alternatives.
            foreach ($parsed as $i => &$j) {
                $j['alternatives'] = array_values(array_map(fn ($o) => [
                    'depart_at' => $o['depart_at'], 'arrive_at' => $o['arrive_at'], 'duration_min' => $o['duration_min'], 'transfers' => $o['transfers'], 'lines' => $o['lines'], 'summary' => $o['summary'],
                ], array_filter($parsed, fn ($o, $k) => $k !== $i, ARRAY_FILTER_USE_BOTH)));
            }
            unset($j);

            return $parsed;
        });
    }

    /**
     * @param array<string,array<string,mixed>> $disruptions
     */
    private function parse(array $journey, array $disruptions): array
    {
        $shape = [];
        $sections = [];
        $maneuvers = [];
        $lines = [];
        $alerts = [];
        $km = 0.0;
        foreach ($journey['sections'] ?? [] as $s) {
            $type = $s['type'] ?? '';
            $coords = array_map(fn ($c) => [round((float) $c[1], 6), round((float) $c[0], 6)], $s['geojson']['coordinates'] ?? []);
            $minutes = (int) round(($s['duration'] ?? 0) / 60);
            $departAt = $this->time($s['departure_date_time'] ?? null);
            $arriveAt = $this->time($s['arrival_date_time'] ?? null);
            if ($type === 'waiting') {
                if ($minutes > 0) {
                    $sections[] = ['type' => 'wait', 'minutes' => $minutes, 'depart_at' => $departAt, 'arrive_at' => $arriveAt];
                }

                continue;
            }
            if ($type === 'crow_fly' && $coords === []) {
                continue;
            }
            $fromName = $this->cleanStop($s['from']['name'] ?? '');
            $toName = $this->cleanStop($s['to']['name'] ?? '');
            $begin = count($shape);
            $shape = array_merge($shape, $coords);
            $sectionKm = $this->lengthKm($coords);
            $km += $sectionKm;

            if ($type === 'public_transport') {
                $di = $s['display_informations'] ?? [];
                $mode = $this->modeLabel($di);
                $code = (string) ($di['code'] ?? $di['label'] ?? '');
                $direction = $this->cleanStop((string) ($di['direction'] ?? ''));
                $stopTimes = $s['stop_date_times'] ?? [];
                $stops = max(0, count($stopTimes) - 1);
                $stopNames = array_values(array_filter(array_map(fn ($st) => $this->cleanStop($st['stop_point']['name'] ?? ''), array_slice($stopTimes, 1)), fn ($n) => $n !== ''));
                $line = ['mode' => $mode, 'code' => $code, 'color' => $this->color($di['color'] ?? ''), 'text_color' => $this->color($di['text_color'] ?? '', '#FFFFFF')];
                $lines[] = $line;
                $sectionAlerts = [];
                foreach ($di['links'] ?? [] as $link) {
                    if (($link['type'] ?? '') === 'disruption' && isset($disruptions[$link['id'] ?? ''])) {
                        // Une même perturbation est souvent publiée plusieurs fois : dédoublonnée sur son texte.
                        $a = $disruptions[$link['id']] + ['line' => trim("$mode $code")];
                        $dedupe = md5(mb_strtolower($a['title'] . '|' . $a['text']));
                        $sectionAlerts[$dedupe] = $a;
                        $alerts[$dedupe] = $a;
                    }
                }
                $sections[] = ['type' => 'pt', 'minutes' => $minutes, 'from' => $fromName, 'to' => $toName, 'stops' => $stops, 'stop_names' => $stopNames, 'direction' => $direction, 'depart_at' => $departAt, 'arrive_at' => $arriveAt, 'headsign' => (string) ($di['headsign'] ?? ''), 'network' => (string) ($di['network'] ?? ''), 'alerts' => array_values($sectionAlerts), 'begin' => $begin, 'end' => count($shape) - 1] + $line;
                $maneuvers[] = [
                    'type' => 40, 'kind' => 'board', 'line' => $line, 'stops' => $stops, 'stop_names' => $stopNames, 'direction' => $direction, 'depart_at' => $departAt, 'arrive_at' => $arriveAt, 'to' => $toName,
                    'text' => __('Prends :line direction :direction', ['line' => trim("$mode $code"), 'direction' => $direction]),
                    'verbal' => __('Prenez :line direction :direction. Descendez à :stop', ['line' => trim($this->article($mode) . " $code"), 'direction' => $direction, 'stop' => $toName]) . ($stops > 0 ? trans_choice(', dans :n arrêt|, dans :n arrêts', $stops, ['n' => $stops]) : '') . '.',
                    'street' => $fromName, 'begin' => $begin, 'end' => count($shape) - 1, 'km' => round($sectionKm, 3), 'sec' => (int) ($s['duration'] ?? 0),
                ];
                $maneuvers[] = [
                    'type' => 41, 'kind' => 'alight', 'line' => $line,
                    'text' => __('Descends à :stop', ['stop' => $toName]),
                    'verbal' => __('Descendez à :stop.', ['stop' => $toName]),
                    'street' => '', 'begin' => count($shape) - 1, 'end' => count($shape) - 1, 'km' => 0, 'sec' => 0,
                ];
            } else {
                $meters = (int) round($sectionKm * 1000);
                $sections[] = ['type' => 'walk', 'minutes' => $minutes, 'from' => $fromName, 'to' => $toName, 'distance_m' => $meters, 'depart_at' => $departAt, 'arrive_at' => $arriveAt, 'transfer' => $type === 'transfer', 'begin' => $begin, 'end' => max($begin, count($shape) - 1)];
                $target = $toName !== '' ? $toName : __('la prochaine étape');
                $maneuvers[] = [
                    'type' => 8, 'kind' => 'walk',
                    'text' => __('Marche :n min jusqu\'à :target', ['n' => $minutes, 'target' => $target]),
                    'verbal' => trans_choice('Marchez :n minute jusqu\'à :target.|Marchez :n minutes jusqu\'à :target.', $minutes, ['n' => $minutes, 'target' => $target]),
                    'street' => '', 'begin' => $begin, 'end' => max($begin, count($shape) - 1), 'km' => round($sectionKm, 3), 'sec' => (int) ($s['duration'] ?? 0),
                ];
            }
        }
        $summary = implode(' → ', array_map(fn ($l) => trim($l['mode'] . ' ' . $l['code']), $lines));

        return [
            'duration_min' => (int) ceil(($journey['duration'] ?? 0) / 60),
            'walking_min' => (int) round(($journey['durations']['walking'] ?? 0) / 60),
            'distance_km' => round($km, 2),
            'depart_at' => $this->time($journey['departure_date_time'] ?? null),
            'arrive_at' => $this->time($journey['arrival_date_time'] ?? null),
            'transfers' => (int) ($journey['nb_transfers'] ?? max(0, count($lines) - 1)),
            'shape' => $shape,
            'sections' => $sections,
            'maneuvers' => $maneuvers,
            'summary' => $summary,
            'lines' => $lines,
            'alerts' => array_values($alerts),
            'alternatives' => [],
        ];
    }

    /**
     * Perturbations de la réponse, indexées par id : {severity, title, text}.
     *
     * @return array<string,array{severity:string,title:string,text:string}>
     */
    private function disruptions(array $raw): array
    {
        $out = [];
        foreach ($raw as $d) {
            $effect = strtoupper((string) ($d['severity']['effect'] ?? ''));
            $severity = match (true) {
                in_array($effect, ['NO_SERVICE', 'REDUCED_SERVICE', 'DETOUR', 'STOP_MOVED'], true) => 'blocking',
                in_array($effect, ['SIGNIFICANT_DELAYS', 'MODIFIED_SERVICE', 'ADDITIONAL_SERVICE'], true) => 'warning',
                default => 'info',
            };
            $text = '';
            foreach ($d['messages'] ?? [] as $m) {
                $t = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', ' ', (string) ($m['text'] ?? '')) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($t !== '' && ($text === '' || mb_strlen($t) > mb_strlen($text))) {
                    $text = $t;
                }
            }
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
            // Ascenseur ou escalator en panne : information utile aux personnes à mobilité réduite, pas une perturbation du trajet.
            if (preg_match('/ascenseur|escalator|escalier m[ée]canique|elevator/iu', $text)) {
                $severity = 'info';
            }
            $out[(string) ($d['id'] ?? uniqid())] = [
                'severity' => $severity,
                'title' => mb_convert_case(trim((string) ($d['severity']['name'] ?? '')) ?: __('Perturbation'), MB_CASE_TITLE, 'UTF-8'),
                'text' => mb_strlen($text) > 220 ? mb_substr($text, 0, 217) . '…' : $text,
            ];
        }

        return $out;
    }

    private function time(?string $navitia): ?string
    {
        if (! $navitia || ! preg_match('/^\d{8}T(\d{2})(\d{2})/', $navitia, $m)) {
            return null;
        }

        return $m[1] . ':' . $m[2];
    }

    private function modeLabel(array $di): string
    {
        $mode = mb_strtolower((string) ($di['physical_mode'] ?? $di['commercial_mode'] ?? ''));

        return match (true) {
            str_contains($mode, 'métro'), str_contains($mode, 'metro') => 'Métro',
            str_contains($mode, 'rer') => 'RER',
            str_contains($mode, 'tram') => 'Tram',
            str_contains($mode, 'bus') => 'Bus',
            str_contains($mode, 'train'), str_contains($mode, 'transilien') => 'Train',
            default => ucfirst($mode ?: 'Ligne'),
        };
    }

    private function article(string $mode): string
    {
        return match ($mode) {
            'Métro', 'RER', 'Tram', 'Train' => app()->getLocale() === 'fr' ? 'le ' . $mode : $mode,
            'Bus' => app()->getLocale() === 'fr' ? 'le bus' : 'Bus',
            default => app()->getLocale() === 'fr' ? 'la ligne' : __('la ligne'),
        };
    }

    private function cleanStop(string $name): string
    {
        return trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $name) ?? $name);
    }

    private function color(string $hex, string $default = '#6B7684'): string
    {
        $hex = ltrim(trim($hex), '#');

        return preg_match('/^[0-9A-Fa-f]{6}$/', $hex) ? '#' . strtoupper($hex) : $default;
    }

    /** @param array<int,array{0:float,1:float}> $coords */
    private function lengthKm(array $coords): float
    {
        $km = 0.0;
        for ($i = 1; $i < count($coords); $i++) {
            $x = deg2rad($coords[$i][1] - $coords[$i - 1][1]) * cos(deg2rad(($coords[$i][0] + $coords[$i - 1][0]) / 2));
            $y = deg2rad($coords[$i][0] - $coords[$i - 1][0]);
            $km += sqrt($x * $x + $y * $y) * 6371;
        }

        return $km;
    }
}
