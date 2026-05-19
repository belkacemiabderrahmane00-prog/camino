<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WikidataClient
{
    private string $sparqlEndpoint;

    public function __construct(?string $sparqlEndpoint = null)
    {
        $this->sparqlEndpoint = $sparqlEndpoint ?: 'https://query.wikidata.org/sparql';
    }

    /**
     * Retourne le nom de fichier d'image principal (P18) pour un QID donné.
     */
    public function getMainImageFilename(string $qid): ?string
    {
        $qid = strtoupper(ltrim($qid, 'Q'));
        if ($qid === '') {
            return null;
        }

        $query = <<<SPARQL
SELECT ?image WHERE {
  wd:Q{$qid} wdt:P18 ?image .
}
LIMIT 1
SPARQL;

        $response = Http::timeout(15)
            ->retry(2, 500)
            ->withHeaders([
                'Accept' => 'application/sparql-results+json',
                'User-Agent' => 'CAMINO/1.0 (contact: webmaster@camino.example)',
            ])
            ->get($this->sparqlEndpoint, [
                'query' => $query,
            ]);

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();
        $bindings = $data['results']['bindings'] ?? [];
        if (! isset($bindings[0]['image']['value'])) {
            return null;
        }

        $url = $bindings[0]['image']['value'];
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $filename = basename($path);
        // Décoder les %20 et homogénéiser avec des underscores, comme sur Commons.
        $filename = str_replace(' ', '_', rawurldecode($filename));

        return $filename;
    }

    /**
     * Recherche des items Wikidata autour d'un point ayant une image P18,
     * et retourne le meilleur candidat (ou null) avec un score de similarité.
     *
     * @return array<string, mixed>|null
     */
    public function findBestNearbyWithImage(
        float $lat,
        float $lng,
        string $name,
        float $radiusKm = 0.4,
        float $minScore = 0.55
    ): ?array {
        $radiusKm = max($radiusKm, 0.05); // au moins 50 m

        $normalizedTarget = $this->normalizeName($name);

        // Wikidata stocke les coordonnées en WKT, avec Point(longitude latitude)
        $centerWkt = sprintf('Point(%F %F)', $lng, $lat);

        $sparql = <<<SPARQL
SELECT ?item ?itemLabel ?coord ?image WHERE {
  SERVICE wikibase:around {
    ?item wdt:P625 ?coord .
    bd:serviceParam wikibase:center "{$centerWkt}"^^geo:wktLiteral .
    bd:serviceParam wikibase:radius "{$radiusKm}" .
  }
  ?item wdt:P18 ?image .
  SERVICE wikibase:label { bd:serviceParam wikibase:language "fr,en". }
}
LIMIT 100
SPARQL;

        try {
            $response = Http::timeout(20)
                ->retry(2, 500)
                ->withHeaders([
                    'Accept' => 'application/sparql-results+json',
                    'User-Agent' => 'CAMINO/1.0 (contact: webmaster@camino.example)',
                ])
                ->get($this->sparqlEndpoint, [
                    'query' => $sparql,
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();
        $bindings = $data['results']['bindings'] ?? [];
        if ($bindings === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($bindings as $row) {
            $itemIri = $row['item']['value'] ?? null;
            $label = $row['itemLabel']['value'] ?? '';
            $coord = $row['coord']['value'] ?? null;
            $imageUrl = $row['image']['value'] ?? null;

            if (! $itemIri || ! $coord || ! $imageUrl) {
                continue;
            }

            // Extraire le QID depuis l'IRI (…/Q12345)
            $qid = strtoupper(basename($itemIri));

            // Extraire lat/lon depuis le WKT "Point(lon lat)"
            $itemLat = null;
            $itemLng = null;
            if (preg_match('/Point\\(([-0-9\\.]+) ([-0-9\\.]+)\\)/', $coord, $m)) {
                $itemLng = (float) $m[1];
                $itemLat = (float) $m[2];
            }

            $distance = $this->computeDistanceMeters($lat, $lng, $itemLat, $itemLng);
            $nameScore = $this->computeNameSimilarity($normalizedTarget, $this->normalizeName($label));
            $distScore = $this->computeDistanceScore($distance);

            $score = round(0.65 * $nameScore + 0.35 * $distScore, 3);

            if ($score < $minScore) {
                continue;
            }

            $path = parse_url($imageUrl, PHP_URL_PATH) ?? $imageUrl;
            $filename = basename($path);
            $filename = str_replace(' ', '_', rawurldecode($filename));

            $candidate = [
                'qid' => $qid,
                'label' => $label,
                'lat' => $itemLat,
                'lng' => $itemLng,
                'distance_m' => $distance,
                'name_score' => $nameScore,
                'distance_score' => $distScore,
                'score' => $score,
                'image_filename' => $filename,
            ];

            if ($best === null || $score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function normalizeName(string $name): string
    {
        $name = Str::lower(Str::ascii($name));

        // Normaliser saint / sainte
        $name = preg_replace('/\\bste?\\b/u', 'sainte', $name);
        $name = preg_replace('/\\bst\\b/u', 'saint', $name);

        // Supprimer ponctuation et caractères spéciaux
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name);

        // Stopwords français courants pour les toponymes
        $stopwords = [
            'le', 'la', 'les', 'de', 'du', 'des', 'd', 'l', 'aux', 'au', 'et',
        ];

        $parts = array_filter(explode(' ', $name));
        $parts = array_values(array_filter($parts, fn ($p) => ! in_array($p, $stopwords, true)));

        return trim(implode(' ', $parts));
    }

    private function computeNameSimilarity(string $target, string $candidate): float
    {
        if ($target === '' || $candidate === '') {
            return 0.3;
        }
        if ($target === $candidate) {
            return 1.0;
        }

        $lev = levenshtein($target, $candidate, 1, 2, 2);
        $maxLen = max(strlen($target), strlen($candidate), 1);

        return max(0.0, 1.0 - ($lev / $maxLen));
    }

    private function computeDistanceScore(?float $distance): float
    {
        if ($distance === null) {
            return 0.6;
        }

        if ($distance <= 50) {
            return 1.0;
        }
        if ($distance <= 150) {
            return 0.8;
        }
        if ($distance <= 300) {
            return 0.5;
        }
        if ($distance <= 600) {
            return 0.3;
        }

        return 0.1;
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

    /**
     * Résout un tag wikipedia=lang:Title en QID Wikidata.
     */
    public function getQidFromWikipediaTag(string $tag): ?string
    {
        $tag = trim($tag);
        if ($tag === '' || ! str_contains($tag, ':')) {
            return null;
        }

        [$lang, $title] = explode(':', $tag, 2);
        $lang = strtolower(trim($lang));
        $title = trim($title);

        if ($lang === '' || $title === '') {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'CAMINO/1.0 (contact: webmaster@camino.example)',
                    'Accept' => 'application/json',
                ])
                ->get('https://www.wikidata.org/w/api.php', [
                    'action' => 'wbgetentities',
                    'sites' => $lang . 'wiki',
                    'titles' => $title,
                    'props' => 'info',
                    'format' => 'json',
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();
        $entities = $data['entities'] ?? [];
        if ($entities === [] || ! is_array($entities)) {
            return null;
        }

        $first = reset($entities);
        $id = $first['id'] ?? null;

        return $id ? strtoupper($id) : null;
    }

    /**
     * Retourne le titre de la sitelink frwiki (ou label fr/en) pour un QID.
     */
    public function getFrenchWikipediaTitle(string $qid): ?string
    {
        $qid = strtoupper(ltrim($qid, 'Q'));
        if ($qid === '') {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->retry(1, 500)
                ->withHeaders([
                    'User-Agent' => 'CAMINO/1.0 (contact: webmaster@camino.example)',
                    'Accept' => 'application/json',
                ])
                ->get('https://www.wikidata.org/w/api.php', [
                    'action' => 'wbgetentities',
                    'ids' => 'Q' . $qid,
                    'props' => 'sitelinks|labels',
                    'sitefilter' => 'frwiki',
                    'format' => 'json',
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();
        $entities = $data['entities'] ?? [];
        $entity = $entities['Q' . $qid] ?? reset($entities);
        if (! is_array($entity)) {
            return null;
        }

        if (! empty($entity['sitelinks']['frwiki']['title'])) {
            return (string) $entity['sitelinks']['frwiki']['title'];
        }

        if (! empty($entity['labels']['fr']['value'])) {
            return (string) $entity['labels']['fr']['value'];
        }

        if (! empty($entity['labels']['en']['value'])) {
            return (string) $entity['labels']['en']['value'];
        }

        return null;
    }

    /**
     * Retourne la catégorie Commons (P373) associée à un QID, si disponible.
     */
    public function getCommonsCategory(string $qid): ?string
    {
        $qid = strtoupper(ltrim($qid, 'Q'));
        if ($qid === '') {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->retry(1, 500)
                ->withHeaders([
                    'User-Agent' => 'CAMINO/1.0 (contact: webmaster@camino.example)',
                    'Accept' => 'application/json',
                ])
                ->get('https://www.wikidata.org/w/api.php', [
                    'action' => 'wbgetentities',
                    'ids' => 'Q' . $qid,
                    'props' => 'claims',
                    'format' => 'json',
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();
        $entities = $data['entities'] ?? [];
        $entity = $entities['Q' . $qid] ?? reset($entities);
        if (! is_array($entity)) {
            return null;
        }

        $claims = $entity['claims']['P373'] ?? null;
        if (! is_array($claims) || empty($claims[0]['mainsnak']['datavalue']['value'])) {
            return null;
        }

        return (string) $claims[0]['mainsnak']['datavalue']['value'];
    }
}

