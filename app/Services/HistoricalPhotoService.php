<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * « Paris d'hier » : photographies anciennes géolocalisées de Wikimedia Commons (données ouvertes, sans clé).
 * Recherche géographique des fichiers autour d'un point, puis lecture de la date de prise de vue ;
 * on ne garde que les images antérieures à 1960. Résultat mis en cache 24 h par cellule.
 */
class HistoricalPhotoService
{
    private const API = 'https://commons.wikimedia.org/w/api.php';
    private const MAX_YEAR = 1960;

    /**
     * @return array<int,array{title:string,year:?int,lat:float,lng:float,thumb:string,image:string,url:string,author:?string,license:?string}>
     */
    public function around(float $lat, float $lng, int $radius = 1200, int $limit = 40): array
    {
        $radius = max(200, min(3000, $radius));
        $key = 'history:v1:' . round($lat, 3) . ':' . round($lng, 3) . ':' . intdiv($radius, 250) . ':' . app()->getLocale();

        return Cache::remember($key, now()->addHours(24), function () use ($lat, $lng, $radius, $limit) {
            try {
                // Recherche plein texte géolocalisée (nearcoord) ciblée sur les décennies anciennes et les grands photographes de Paris.
                $search = sprintf('nearcoord:%dm,%s,%s (1850 OR 1860 OR 1870 OR 1880 OR 1890 OR 1900 OR 1910 OR 1920 OR 1930 OR 1940 OR 1950 OR Atget OR Marville OR "carte postale" OR postcard OR ancienne OR vintage)', $radius, round($lat, 5), round($lng, 5));
                $response = Http::timeout((int) config('camino.history.timeout', 15))
                    ->withHeaders(['User-Agent' => config('camino.user_agent'), 'Accept-Encoding' => 'gzip'])
                    ->get(self::API, [
                        'action' => 'query', 'format' => 'json', 'formatversion' => 2,
                        'generator' => 'search', 'gsrsearch' => $search, 'gsrnamespace' => 6, 'gsrlimit' => 50,
                        'prop' => 'imageinfo|coordinates', 'colimit' => 'max', 'iiprop' => 'url|extmetadata|mime', 'iiurlwidth' => 900,
                        'iiextmetadatafilter' => 'DateTimeOriginal|Artist|LicenseShortName|ObjectName',
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Commons unavailable: ' . $e->getMessage());

                return [];
            }
            if (! $response->ok()) {
                return [];
            }
            $out = [];
            foreach ($response->json('query.pages', []) as $page) {
                $info = $page['imageinfo'][0] ?? null;
                $coord = $page['coordinates'][0] ?? null;
                if (! $info || ! $coord) {
                    continue;
                }
                $meta = $info['extmetadata'] ?? [];
                $year = $this->year((string) ($meta['DateTimeOriginal']['value'] ?? ''), (string) ($page['title'] ?? ''));
                if ($year === null || $year > self::MAX_YEAR) {
                    continue;
                }
                $mime = (string) ($info['mime'] ?? '');
                if (! preg_match('/\.(jpe?g|png|tiff?)$/i', (string) $page['title']) && ! str_starts_with($mime, 'image/')) {
                    continue;
                }
                $title = trim(strip_tags((string) ($meta['ObjectName']['value'] ?? '')));
                if ($title === '') {
                    $title = preg_replace('/^File:|\.[a-z]+$/i', '', (string) $page['title']);
                }
                $out[] = [
                    'title' => mb_substr($title, 0, 120),
                    'year' => $year,
                    'lat' => (float) $coord['lat'],
                    'lng' => (float) $coord['lon'],
                    'thumb' => (string) ($info['thumburl'] ?? $info['url']),
                    'image' => (string) ($info['thumburl'] ?? $info['url']),
                    'url' => (string) ($info['descriptionurl'] ?? ('https://commons.wikimedia.org/wiki/' . rawurlencode((string) $page['title']))),
                    'author' => $this->clean($meta['Artist']['value'] ?? null),
                    'license' => $this->clean($meta['LicenseShortName']['value'] ?? null),
                ];
            }
            usort($out, fn ($a, $b) => $a['year'] <=> $b['year']);

            return array_slice($out, 0, $limit);
        });
    }

    private function year(string $date, string $title): ?int
    {
        // Date de prise de vue d'abord ; sinon une année 1600–1959 dans le titre (les titres modernes citent parfois une taille en pixels).
        if (preg_match('/\b(1[6-9]\d\d|20[0-2]\d)\b/', strip_tags($date), $m)) {
            return (int) $m[1];
        }
        if (preg_match('/(?<![\d.])(1[6-9]\d\d)(?![\d]|\s?px)/', $title, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return $t === '' ? null : mb_substr($t, 0, 80);
    }
}
