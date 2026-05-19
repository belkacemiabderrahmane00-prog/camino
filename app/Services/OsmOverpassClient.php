<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OsmOverpassClient
{
    private string $endpoint;

    public function __construct(?string $endpoint = null)
    {
        $this->endpoint = $endpoint ?: 'https://overpass-api.de/api/interpreter';
    }

    /**
     * Recherche des objets OSM autour d'un point pour un lieu donné.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchAround(float $lat, float $lng, string $name, int $radiusMeters = 250): array
    {
        // On ne combine pas tous les tags (tourism/amenity/historic/leisure) sur le même objet,
        // on fait une union de types pour couvrir musées, monuments, parcs, restos, etc.
        $query = <<<OVERPASS
[out:json][timeout:25];
(
  node[tourism~"museum|gallery|attraction|zoo|artwork"](around:$radiusMeters,$lat,$lng);
  node[amenity~"place_of_worship|theatre|arts_centre|cinema|library|restaurant|cafe"](around:$radiusMeters,$lat,$lng);
  node[historic](around:$radiusMeters,$lat,$lng);
  node[leisure~"park|garden"](around:$radiusMeters,$lat,$lng);

  way[tourism~"museum|gallery|attraction|zoo|artwork"](around:$radiusMeters,$lat,$lng);
  way[amenity~"place_of_worship|theatre|arts_centre|cinema|library|restaurant|cafe"](around:$radiusMeters,$lat,$lng);
  way[historic](around:$radiusMeters,$lat,$lng);
  way[leisure~"park|garden"](around:$radiusMeters,$lat,$lng);

  relation[tourism~"museum|gallery|attraction|zoo|artwork"](around:$radiusMeters,$lat,$lng);
  relation[amenity~"place_of_worship|theatre|arts_centre|cinema|library|restaurant|cafe"](around:$radiusMeters,$lat,$lng);
  relation[historic](around:$radiusMeters,$lat,$lng);
  relation[leisure~"park|garden"](around:$radiusMeters,$lat,$lng);
);
out body;
>;
out skel qt;
OVERPASS;

        try {
            $response = Http::timeout(20)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'CAMINO/1.0 (contact: webmaster@camino.example)',
                    'Accept' => 'application/json',
                ])
                ->asForm()
                ->post($this->endpoint, [
                    'data' => $query,
                ]);
        } catch (\Throwable $e) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        $data = $response->json();
        $elements = $data['elements'] ?? [];

        $normalizedTarget = $this->normalizeName($name);

        $results = [];
        foreach ($elements as $el) {
            if (! isset($el['id'], $el['type'])) {
                continue;
            }
            $tags = $el['tags'] ?? [];
            $candidateName = $tags['name'] ?? $tags['name:fr'] ?? $tags['name:en'] ?? '';

            $distance = $this->computeDistanceMeters($lat, $lng, $el['lat'] ?? null, $el['lon'] ?? null);
            $score = $this->computeMatchScore($normalizedTarget, $this->normalizeName($candidateName), $distance);

            $results[] = [
                'osm_type' => $el['type'],
                'osm_id' => $el['id'],
                'name' => $candidateName,
                'tags' => $tags,
                'distance_m' => $distance,
                'score' => $score,
            ];
        }

        usort($results, fn ($a, $b) => ($b['score'] <=> $a['score']));

        return $results;
    }

    private function normalizeName(string $name): string
    {
        $name = Str::lower(Str::ascii($name));
        $name = preg_replace('/\b(musee|museum|eglise|church|cathedrale|theatre|galerie|gallery|paris)\b/u', '', $name);
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name);

        return trim($name ?? '');
    }

    private function computeMatchScore(string $target, string $candidate, ?float $distance): float
    {
        if ($candidate === '') {
            $nameScore = 0.2;
        } elseif ($target === $candidate) {
            $nameScore = 1.0;
        } else {
            $lev = levenshtein($target, $candidate, 1, 2, 2);
            $maxLen = max(strlen($target), strlen($candidate), 1);
            $nameScore = max(0.0, 1.0 - ($lev / $maxLen));
        }

        $distScore = 1.0;
        if ($distance !== null) {
            if ($distance <= 50) {
                $distScore = 1.0;
            } elseif ($distance <= 150) {
                $distScore = 0.7;
            } elseif ($distance <= 300) {
                $distScore = 0.4;
            } else {
                $distScore = 0.1;
            }
        }

        return round(($nameScore * 0.7 + $distScore * 0.3), 3);
    }

    private function computeDistanceMeters(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return null;
        }
        $earthRadius = 6371000; // mètres
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}

