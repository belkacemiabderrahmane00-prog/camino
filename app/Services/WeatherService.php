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
                Log::warning('Weather unavailable: ' . $e->getMessage());

                return $this->unavailable();
            }

            if (! $response->ok()) {
                return $this->unavailable();
            }

            return $this->parse($response->json());
        })();

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
