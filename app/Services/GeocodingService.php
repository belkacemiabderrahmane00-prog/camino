<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Géocodage via l'API Adresse (Base Adresse Nationale, gratuite, sans clé).
 * Résultats mis en cache ; recherche biaisée vers l'Île-de-France.
 */
class GeocodingService
{
    private const BASE = 'https://api-adresse.data.gouv.fr';

    /**
     * @return array<int,array{label:string,city:?string,type:string,lat:float,lng:float}>
     */
    public function search(string $query, ?float $lat = null, ?float $lng = null, int $limit = 6): array
    {
        $query = trim(mb_substr($query, 0, 120));
        if (mb_strlen($query) < 3) {
            return [];
        }
        $key = 'geocode:' . md5(mb_strtolower($query) . '|' . ($lat ? round($lat, 2) : '') . '|' . ($lng ? round($lng, 2) : ''));

        return Cache::remember($key, now()->addDays(7), function () use ($query, $lat, $lng, $limit) {
            $params = ['q' => $query, 'limit' => $limit, 'autocomplete' => 1];
            if ($lat && $lng) {
                $params['lat'] = $lat;
                $params['lon'] = $lng;
            }
            try {
                $response = Http::timeout(8)->withHeaders(['User-Agent' => config('camino.user_agent')])->get(self::BASE . '/search/', $params);
            } catch (\Throwable $e) {
                Log::warning('Geocoding unavailable: ' . $e->getMessage());

                return [];
            }
            if (! $response->ok()) {
                return [];
            }
            $out = [];
            foreach ($response->json('features', []) as $f) {
                $p = $f['properties'] ?? [];
                $c = $f['geometry']['coordinates'] ?? null;
                if (! $c || ! isset($p['label'])) {
                    continue;
                }
                // On reste en Île-de-France (codes postaux 75, 77, 78, 91, 92, 93, 94, 95).
                $dept = substr((string) ($p['postcode'] ?? ''), 0, 2);
                if ($dept !== '' && ! in_array($dept, ['75', '77', '78', '91', '92', '93', '94', '95'], true)) {
                    continue;
                }
                $out[] = [
                    'label' => (string) $p['label'],
                    'city' => $p['city'] ?? null,
                    'type' => (string) ($p['type'] ?? 'street'),
                    'lat' => (float) $c[1],
                    'lng' => (float) $c[0],
                ];
            }

            return $out;
        });
    }

    /** Libellé court d'un point (rue + ville), ou null. */
    public function reverse(float $lat, float $lng): ?string
    {
        $key = 'revgeo:' . round($lat, 4) . ',' . round($lng, 4);

        return Cache::remember($key, now()->addDays(30), function () use ($lat, $lng) {
            try {
                $response = Http::timeout(8)->withHeaders(['User-Agent' => config('camino.user_agent')])->get(self::BASE . '/reverse/', ['lat' => $lat, 'lon' => $lng, 'limit' => 1]);
            } catch (\Throwable $e) {
                return null;
            }
            $p = $response->ok() ? ($response->json('features.0.properties') ?? null) : null;
            if (! $p) {
                return null;
            }

            return trim(($p['name'] ?? '') . ($p['city'] ?? '' ? ', ' . $p['city'] : '')) ?: ($p['label'] ?? null);
        });
    }
}
