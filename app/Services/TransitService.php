<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transports en commun via l'API PRIM d'Île-de-France Mobilités (calculateur Navitia).
 * Un trajet = marche → métro/RER/bus/tram → marche, avec tracé, lignes, directions et instructions.
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
     * @return array{duration_min:int,walking_min:int,distance_km:float,shape:array<int,array{0:float,1:float}>,sections:array<int,array<string,mixed>>,maneuvers:array<int,array<string,mixed>>,summary:string,lines:array<int,array{mode:string,code:string,color:string,text_color:string}>}|null
     */
    public function journey(array $from, array $to, Carbon $at): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        // Les horaires changent peu à l'échelle d'un quart d'heure : cache par créneau.
        $slot = $at->copy()->minute(intdiv($at->minute, 15) * 15)->second(0);
        $key = 'transit:v1:' . md5(round($from['lat'], 4) . ',' . round($from['lng'], 4) . '|' . round($to['lat'], 4) . ',' . round($to['lng'], 4) . '|' . $slot->format('YmdHi'));

        return Cache::remember($key, now()->addMinutes((int) config('camino.transit.cache_minutes', 60)), function () use ($from, $to, $slot) {
            try {
                $response = Http::timeout((int) config('camino.transit.timeout', 10))
                    ->withHeaders(['apiKey' => config('camino.transit.api_key'), 'Accept' => 'application/json', 'User-Agent' => config('camino.user_agent')])
                    ->get(rtrim(config('camino.transit.base_url'), '/') . '/journeys', [
                        'from' => round($from['lng'], 6) . ';' . round($from['lat'], 6),
                        'to' => round($to['lng'], 6) . ';' . round($to['lat'], 6),
                        'datetime' => $slot->format('Ymd\THis'),
                        'max_nb_journeys' => 3,
                        'max_walking_duration_to_pt' => 900,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Transit unavailable: ' . $e->getMessage());

                return null;
            }
            if (! $response->ok()) {
                Log::warning('Transit error: HTTP ' . $response->status() . ' ' . substr($response->body(), 0, 160));

                return null;
            }
            foreach ($response->json('journeys', []) as $journey) {
                $sections = $journey['sections'] ?? [];
                $hasPt = collect($sections)->contains(fn ($s) => ($s['type'] ?? '') === 'public_transport');
                if (! $hasPt) {
                    continue;
                }

                return $this->parse($journey);
            }

            return null;
        });
    }

    private function parse(array $journey): array
    {
        $shape = [];
        $sections = [];
        $maneuvers = [];
        $lines = [];
        $km = 0.0;
        foreach ($journey['sections'] ?? [] as $s) {
            $type = $s['type'] ?? '';
            $coords = array_map(fn ($c) => [round((float) $c[1], 6), round((float) $c[0], 6)], $s['geojson']['coordinates'] ?? []);
            if ($type === 'waiting' || $type === 'transfer' || $type === 'crow_fly') {
                if ($coords !== [] && $type !== 'waiting') {
                    $shape = array_merge($shape, $coords);
                }
                if ($type === 'waiting') {
                    $sections[] = ['type' => 'wait', 'minutes' => (int) round(($s['duration'] ?? 0) / 60)];
                }

                continue;
            }
            $minutes = (int) round(($s['duration'] ?? 0) / 60);
            $fromName = $this->cleanStop($s['from']['name'] ?? '');
            $toName = $this->cleanStop($s['to']['name'] ?? '');
            $begin = count($shape);
            $shape = array_merge($shape, $coords);
            $km += $this->lengthKm($coords);

            if ($type === 'public_transport') {
                $di = $s['display_informations'] ?? [];
                $mode = $this->modeLabel($di);
                $code = (string) ($di['code'] ?? $di['label'] ?? '');
                $direction = $this->cleanStop((string) ($di['direction'] ?? ''));
                $stops = max(0, count($s['stop_date_times'] ?? []) - 1);
                $line = ['mode' => $mode, 'code' => $code, 'color' => $this->color($di['color'] ?? ''), 'text_color' => $this->color($di['text_color'] ?? '', '#FFFFFF')];
                $lines[] = $line;
                $sections[] = ['type' => 'pt', 'minutes' => $minutes, 'from' => $fromName, 'to' => $toName, 'stops' => $stops, 'direction' => $direction] + $line;
                $maneuvers[] = [
                    'type' => 40, 'kind' => 'board', 'line' => $line,
                    'text' => "Prends $mode $code direction $direction",
                    'verbal' => "Prenez " . $this->article($mode) . " $code direction $direction. Descendez à $toName" . ($stops > 0 ? ", dans $stops arrêt" . ($stops > 1 ? 's' : '') : '') . '.',
                    'street' => $fromName, 'begin' => $begin, 'end' => count($shape) - 1, 'km' => round($this->lengthKm($coords), 3), 'sec' => (int) ($s['duration'] ?? 0),
                ];
                $maneuvers[] = [
                    'type' => 41, 'kind' => 'alight', 'line' => $line,
                    'text' => "Descends à $toName",
                    'verbal' => "Descendez à $toName.",
                    'street' => '', 'begin' => count($shape) - 1, 'end' => count($shape) - 1, 'km' => 0, 'sec' => 0,
                ];
            } else {
                $sections[] = ['type' => 'walk', 'minutes' => $minutes, 'from' => $fromName, 'to' => $toName];
                $target = $toName !== '' ? $toName : 'la prochaine étape';
                $maneuvers[] = [
                    'type' => 8, 'kind' => 'walk',
                    'text' => "Marche $minutes min jusqu'à $target",
                    'verbal' => "Marchez $minutes minute" . ($minutes > 1 ? 's' : '') . " jusqu'à $target.",
                    'street' => '', 'begin' => $begin, 'end' => max($begin, count($shape) - 1), 'km' => round($this->lengthKm($coords), 3), 'sec' => (int) ($s['duration'] ?? 0),
                ];
            }
        }
        $summary = implode(' → ', array_map(fn ($l) => trim($l['mode'] . ' ' . $l['code']), $lines));

        return [
            'duration_min' => (int) ceil(($journey['duration'] ?? 0) / 60),
            'walking_min' => (int) round(($journey['durations']['walking'] ?? 0) / 60),
            'distance_km' => round($km, 2),
            'shape' => $shape,
            'sections' => $sections,
            'maneuvers' => $maneuvers,
            'summary' => $summary,
            'lines' => $lines,
        ];
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
            'Métro', 'RER', 'Tram', 'Train' => 'le ' . $mode,
            'Bus' => 'le bus',
            default => 'la ligne',
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
