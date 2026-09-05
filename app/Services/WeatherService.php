<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Météo via Open-Meteo (sans clé). Résultats mis en cache 30 minutes par zone (~1 km).
 */
class WeatherService
{
    /** Dernière erreur rencontrée (diagnostic, exposée par l'API météo quand la prévision est indisponible). */
    public ?string $lastError = null;

    /** Codes WMO → libellé français + icône Material Symbols + indicateur "temps à intérieur". */
    private const CODES = [
        0 => ['Ciel dégagé', 'clear_day', false],
        1 => ['Plutôt dégagé', 'partly_cloudy_day', false],
        2 => ['Partiellement nuageux', 'partly_cloudy_day', false],
        3 => ['Couvert', 'cloud', false],
        45 => ['Brouillard', 'foggy', false],
        48 => ['Brouillard givrant', 'foggy', false],
        51 => ['Bruine légère', 'rainy_light', true],
        53 => ['Bruine', 'rainy_light', true],
        55 => ['Bruine dense', 'rainy', true],
        61 => ['Pluie légère', 'rainy_light', true],
        63 => ['Pluie', 'rainy', true],
        65 => ['Pluie forte', 'rainy_heavy', true],
        66 => ['Pluie verglaçante', 'rainy_snow', true],
        67 => ['Pluie verglaçante forte', 'rainy_snow', true],
        71 => ['Neige légère', 'weather_snowy', true],
        73 => ['Neige', 'weather_snowy', true],
        75 => ['Neige forte', 'weather_snowy', true],
        77 => ['Grésil', 'weather_snowy', true],
        80 => ['Averses légères', 'rainy_light', true],
        81 => ['Averses', 'rainy', true],
        82 => ['Averses violentes', 'rainy_heavy', true],
        85 => ['Averses de neige', 'weather_snowy', true],
        86 => ['Fortes averses de neige', 'weather_snowy', true],
        95 => ['Orage', 'thunderstorm', true],
        96 => ['Orage avec grêle', 'thunderstorm', true],
        99 => ['Orage violent', 'thunderstorm', true],
    ];

    /**
     * @return array{
     *   current: array{temp:float,code:int,label:string,icon:string,precipitation:float,wind:float,indoor:bool}|null,
     *   hours: array<int,array{time:string,temp:float,code:int,label:string,icon:string,rain_probability:int,indoor:bool}>,
     *   days: array<int,array{date:string,code:int,label:string,icon:string,tmin:float,tmax:float,rain_probability:int}>,
     *   available: bool
     * }
     */
    public function forecast(float $lat, float $lng): array
    {
        $key = sprintf('weather:%.2f:%.2f', $lat, $lng);

        $cached = Cache::get($key);
        if (is_array($cached) && ($cached['available'] ?? false)) {
            return $cached;
        }

        $forecast = (function () use ($lat, $lng) {
            try {
                $response = Http::timeout(config('camino.weather.timeout', 10))
                    ->withHeaders(['User-Agent' => config('camino.user_agent')])
                    ->get(config('camino.weather.base_url'), [
                        'latitude' => round($lat, 3),
                        'longitude' => round($lng, 3),
                        'current' => 'temperature_2m,weather_code,precipitation,wind_speed_10m',
                        'hourly' => 'temperature_2m,precipitation_probability,weather_code',
                        'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
                        'forecast_days' => 3,
                        'timezone' => config('app.timezone', 'Europe/Paris'),
                    ]);
            } catch (\Throwable $e) {
                $this->lastError = get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 160);
                Log::warning('Weather unavailable: ' . $e->getMessage());

                return $this->unavailable();
            }

            if (! $response->ok()) {
                $this->lastError = 'HTTP ' . $response->status() . ' ' . mb_substr($response->body(), 0, 160);
                Log::warning('Weather unavailable: ' . $this->lastError);

                return $this->unavailable();
            }

            return $this->parse($response->json());
        })();

        // Repli : MET Norway (Locationforecast, sans clé) quand Open-Meteo est indisponible ou limité (429).
        if (! $forecast['available']) {
            $forecast = $this->fetchMetNo($lat, $lng);
        }

        // Seuls les résultats valides sont mis en cache : une panne ne doit pas masquer la météo 30 minutes.
        if ($forecast['available']) {
            Cache::put($key, $forecast, now()->addMinutes(config('camino.weather.cache_minutes', 30)));
        }

        return $forecast;
    }

    /**
     * Résumé météo pour une fenêtre horaire (ex. durée du parcours) : pluie probable ou non.
     *
     * @return array{indoor_recommended:bool,rain_probability:int,label:string,icon:string,temp:float|null}
     */
    public function summaryFor(float $lat, float $lng, Carbon $start, int $durationMin): array
    {
        $forecast = $this->forecast($lat, $lng);
        $end = $start->copy()->addMinutes(max($durationMin, 60));
        $window = array_filter($forecast['hours'], function (array $h) use ($start, $end) {
            $t = Carbon::parse($h['time'], config('app.timezone'));

            return $t->between($start->copy()->subHour(), $end);
        });

        if ($window === []) {
            $current = $forecast['current'];

            return [
                'indoor_recommended' => (bool) ($current['indoor'] ?? false),
                'rain_probability' => 0,
                'label' => $current['label'] ?? 'Météo indisponible',
                'icon' => $current['icon'] ?? 'cloud',
                'temp' => $current['temp'] ?? null,
            ];
        }

        $maxRain = max(array_column($window, 'rain_probability'));
        $worst = collect($window)->sortByDesc(fn ($h) => ($h['indoor'] ? 100 : 0) + $h['rain_probability'])->first();
        $avgTemp = round(array_sum(array_column($window, 'temp')) / count($window), 1);

        return [
            'indoor_recommended' => $maxRain >= 50 || (bool) $worst['indoor'],
            'rain_probability' => (int) $maxRain,
            'label' => $worst['label'],
            'icon' => $worst['icon'],
            'temp' => $avgTemp,
        ];
    }

    private function parse(array $data): array
    {
        $current = null;
        if (isset($data['current']['weather_code'])) {
            [$label, $icon, $indoor] = $this->describe((int) $data['current']['weather_code']);
            $current = [
                'temp' => round((float) $data['current']['temperature_2m'], 1),
                'code' => (int) $data['current']['weather_code'],
                'label' => $label,
                'icon' => $icon,
                'precipitation' => (float) ($data['current']['precipitation'] ?? 0),
                'wind' => (float) ($data['current']['wind_speed_10m'] ?? 0),
                'indoor' => $indoor,
            ];
        }

        $hours = [];
        foreach ($data['hourly']['time'] ?? [] as $i => $time) {
            $code = (int) ($data['hourly']['weather_code'][$i] ?? 0);
            [$label, $icon, $indoor] = $this->describe($code);
            $hours[] = [
                'time' => $time,
                'temp' => round((float) ($data['hourly']['temperature_2m'][$i] ?? 0), 1),
                'code' => $code,
                'label' => $label,
                'icon' => $icon,
                'rain_probability' => (int) ($data['hourly']['precipitation_probability'][$i] ?? 0),
                'indoor' => $indoor,
            ];
        }

        $days = [];
        foreach ($data['daily']['time'] ?? [] as $i => $date) {
            $code = (int) ($data['daily']['weather_code'][$i] ?? 0);
            [$label, $icon] = $this->describe($code);
            $days[] = [
                'date' => $date,
                'code' => $code,
                'label' => $label,
                'icon' => $icon,
                'tmin' => round((float) ($data['daily']['temperature_2m_min'][$i] ?? 0)),
                'tmax' => round((float) ($data['daily']['temperature_2m_max'][$i] ?? 0)),
                'rain_probability' => (int) ($data['daily']['precipitation_probability_max'][$i] ?? 0),
            ];
        }

        return ['current' => $current, 'hours' => $hours, 'days' => $days, 'available' => true];
    }

    /**
     * Conseil du moment, pour l'app mobile : une phrase courte et une orientation (dehors / à l'abri).
     *
     * @return array{title:string,text:string,icon:string,tone:string,indoor:bool,temp:?float,label:?string}
     */
    public function advice(array $forecast): array
    {
        $current = $forecast['current'] ?? null;
        if (! $current) {
            return ['title' => 'Météo indisponible', 'text' => 'On te conseille quand même une balade : la ville n\'attend pas.', 'icon' => 'cloud', 'tone' => 'neutral', 'indoor' => false, 'temp' => null, 'label' => null];
        }

        $now = Carbon::now(config('app.timezone'));
        $next = array_values(array_filter($forecast['hours'], fn ($h) => Carbon::parse($h['time'], config('app.timezone'))->between($now, $now->copy()->addHours(4))));
        $rainSoon = $next !== [] ? max(array_column($next, 'rain_probability')) : 0;
        $temp = (float) $current['temp'];

        if ($current['indoor'] || $rainSoon >= 60) {
            return ['title' => 'Plutôt à l\'abri', 'text' => $current['indoor'] ? 'Il pleut : direction musées, galeries et cafés. Le générateur privilégie les lieux couverts.' : sprintf('%d %% de pluie dans les prochaines heures : garde un musée sous le coude.', $rainSoon), 'icon' => $current['icon'], 'tone' => 'rain', 'indoor' => true, 'temp' => $temp, 'label' => $current['label']];
        }
        if ($temp >= 27) {
            return ['title' => 'Il fait chaud', 'text' => 'Parcs ombragés le matin, musées frais l\'après-midi. Pense à une gourde.', 'icon' => $current['icon'], 'tone' => 'hot', 'indoor' => false, 'temp' => $temp, 'label' => $current['label']];
        }
        if ($temp <= 5) {
            return ['title' => 'Ça pique', 'text' => 'Balade courte entre deux lieux couverts, et un chocolat chaud au milieu.', 'icon' => $current['icon'], 'tone' => 'cold', 'indoor' => true, 'temp' => $temp, 'label' => $current['label']];
        }
        if (in_array((int) $current['code'], [0, 1], true)) {
            return ['title' => 'Journée parfaite dehors', 'text' => 'Parcs, jardins, street art : c\'est le moment de marcher. Le générateur le sait.', 'icon' => $current['icon'], 'tone' => 'sun', 'indoor' => false, 'temp' => $temp, 'label' => $current['label']];
        }

        return ['title' => 'Bonne journée pour explorer', 'text' => 'Ciel voilé, températures douces : idéal pour enchaîner monuments et jardins.', 'icon' => $current['icon'], 'tone' => 'mild', 'indoor' => false, 'temp' => $temp, 'label' => $current['label']];
    }

    /**
     * MET Norway Locationforecast 2.0 (compact) → même structure que Open-Meteo.
     * Les codes symboliques MET sont convertis en codes WMO pour réutiliser describe().
     */
    private function fetchMetNo(float $lat, float $lng): array
    {
        try {
            $response = Http::timeout(config('camino.weather.timeout', 10))
                ->withHeaders(['User-Agent' => config('camino.user_agent') . ' contact: webmaster@camino.app'])
                ->get('https://api.met.no/weatherapi/locationforecast/2.0/compact', [
                    'lat' => round($lat, 3),
                    'lon' => round($lng, 3),
                ]);
        } catch (\Throwable $e) {
            $this->lastError .= ' | met.no: ' . mb_substr($e->getMessage(), 0, 120);

            return $this->unavailable();
        }

        if (! $response->ok()) {
            $this->lastError .= ' | met.no: HTTP ' . $response->status();

            return $this->unavailable();
        }

        $series = $response->json()['properties']['timeseries'] ?? [];
        if ($series === []) {
            return $this->unavailable();
        }

        $tz = config('app.timezone', 'Europe/Paris');
        $hours = [];
        $byDay = [];
        foreach ($series as $entry) {
            $time = Carbon::parse($entry['time'])->setTimezone($tz);
            $details = $entry['data']['instant']['details'] ?? [];
            $next = $entry['data']['next_1_hours'] ?? $entry['data']['next_6_hours'] ?? null;
            $symbol = (string) ($next['summary']['symbol_code'] ?? 'cloudy');
            $precip = (float) ($next['details']['precipitation_amount'] ?? 0);
            $code = $this->metSymbolToWmo($symbol);
            [$label, $icon, $indoor] = $this->describe($code);
            $rainProbability = $precip <= 0 ? 5 : ($precip < 0.5 ? 40 : ($precip < 2 ? 70 : 90));
            $temp = round((float) ($details['air_temperature'] ?? 0), 1);

            if (count($hours) < 72) {
                $hours[] = [
                    'time' => $time->format('Y-m-d\TH:i'),
                    'temp' => $temp,
                    'code' => $code,
                    'label' => $label,
                    'icon' => $icon,
                    'rain_probability' => $rainProbability,
                    'indoor' => $indoor,
                ];
            }
            $day = $time->toDateString();
            $byDay[$day]['temps'][] = $temp;
            $byDay[$day]['rain'][] = $rainProbability;
            if ($time->hour >= 11 && $time->hour <= 15 && ! isset($byDay[$day]['code'])) {
                $byDay[$day]['code'] = $code;
            }
        }

        $days = [];
        foreach (array_slice($byDay, 0, 3, true) as $date => $d) {
            $code = $d['code'] ?? $this->metSymbolToWmo('cloudy');
            [$label, $icon] = $this->describe($code);
            $days[] = [
                'date' => $date,
                'code' => $code,
                'label' => $label,
                'icon' => $icon,
                'tmin' => round(min($d['temps'])),
                'tmax' => round(max($d['temps'])),
                'rain_probability' => (int) max($d['rain']),
            ];
        }

        $first = $hours[0] ?? null;
        $current = $first ? [
            'temp' => $first['temp'],
            'code' => $first['code'],
            'label' => $first['label'],
            'icon' => $first['icon'],
            'precipitation' => 0.0,
            'wind' => round((float) ($series[0]['data']['instant']['details']['wind_speed'] ?? 0) * 3.6, 1),
            'indoor' => $first['indoor'],
        ] : null;

        return ['current' => $current, 'hours' => $hours, 'days' => $days, 'available' => $current !== null, 'provider' => 'met.no'];
    }

    private function metSymbolToWmo(string $symbol): int
    {
        $base = preg_replace('/_(day|night|polartwilight)$/', '', $symbol) ?? $symbol;

        return match (true) {
            $base === 'clearsky' => 0,
            $base === 'fair' => 1,
            $base === 'partlycloudy' => 2,
            $base === 'cloudy' => 3,
            $base === 'fog' => 45,
            str_contains($base, 'thunder') => 95,
            str_starts_with($base, 'lightrain') || str_starts_with($base, 'lightsleet') => 61,
            str_starts_with($base, 'heavyrain') => 65,
            str_starts_with($base, 'rain') || str_contains($base, 'sleet') => 63,
            str_contains($base, 'snow') => 73,
            default => 3,
        };
    }

    /** @return array{0:string,1:string,2:bool} */
    private function describe(int $code): array
    {
        return self::CODES[$code] ?? ['Variable', 'cloud', false];
    }

    private function unavailable(): array
    {
        return ['current' => null, 'hours' => [], 'days' => [], 'available' => false];
    }
}
