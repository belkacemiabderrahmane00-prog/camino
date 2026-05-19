<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenverseClient
{
    private string $endpoint;

    public function __construct(?string $endpoint = null)
    {
        $this->endpoint = $endpoint ?: 'https://api.openverse.org/v1/images/';
    }

    /**
     * Recherche une image Openverse pour un lieu donné et retourne
     * le meilleur candidat au-dessus d'un score minimal.
     *
     * @return array<string, mixed>|null
     */
    public function findBestImageForPlace(
        string $title,
        ?string $address = null,
        float $minScore = 0.6
    ): ?array {
        $minScore = max(0.0, min($minScore, 1.0));

        $queryParts = [$title];
        if ($address) {
            $queryParts[] = $address;
        }
        $query = trim(implode(' ', $queryParts));

        if ($query === '') {
            return null;
        }

        $normalizedTarget = $this->normalizeText($title);

        try {
            $response = Http::timeout(20)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'CAMINO/1.0 (contact: webmaster@camino.example)',
                    'Accept' => 'application/json',
                ])
                ->get($this->endpoint, [
                    'q' => $query,
                    'page_size' => 20,
                    'format' => 'json',
                    'license_type' => 'cc0,cc-by,cc-by-sa',
                    'mature' => 'false',
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();
        $results = $data['results'] ?? [];
        if (! is_array($results) || $results === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($results as $item) {
            if (! is_array($item)) {
                continue;
            }

            $titleCandidate = (string) ($item['title'] ?? '');
            $tags = $item['tags'] ?? [];
            $tagsText = '';
            if (is_array($tags)) {
                $tagNames = [];
                foreach ($tags as $tag) {
                    if (is_array($tag) && isset($tag['name'])) {
                        $tagNames[] = (string) $tag['name'];
                    }
                }
                $tagsText = implode(' ', $tagNames);
            }

            $text = trim($titleCandidate . ' ' . $tagsText);
            $normalizedCandidate = $this->normalizeText($text);

            $nameScore = $this->computeNameSimilarity($normalizedTarget, $normalizedCandidate);

            $score = round($nameScore, 3);
            if ($score < $minScore) {
                continue;
            }

            if ($best === null || $score > $bestScore) {
                $best = $item;
                $bestScore = $score;
            }
        }

        if (! $best) {
            return null;
        }

        $licenseCode = $best['license'] ?? null; // ex: "by-sa"
        $licenseVersion = $best['license_version'] ?? null;
        $licenseLabel = null;
        if ($licenseCode) {
            $licenseLabel = 'CC ' . strtoupper(str_replace('-', '-', (string) $licenseCode));
            if ($licenseVersion) {
                $licenseLabel .= ' ' . $licenseVersion;
            }
        }

        return [
            'id' => $best['id'] ?? null,
            'title' => $best['title'] ?? null,
            'image_url_original' => $best['url'] ?? null,
            'image_url_thumb' => $best['thumbnail'] ?? null,
            'author' => $best['creator'] ?? null,
            'license' => $licenseLabel,
            'license_url' => $best['license_url'] ?? null,
            'attribution' => $best['attribution'] ?? null,
            'page_url' => $best['foreign_landing_url'] ?? ($best['detail_url'] ?? null),
            'provider' => $best['provider'] ?? null,
            'source' => $best['source'] ?? null,
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = Str::lower(Str::ascii($text));

        // Normaliser saint / sainte
        $text = preg_replace('/\bste?\b/u', 'sainte', $text);
        $text = preg_replace('/\bst\b/u', 'saint', $text);

        // Supprimer ponctuation et caractères spéciaux
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text);

        // Stopwords français courants
        $stopwords = [
            'le', 'la', 'les', 'de', 'du', 'des', 'd', 'l', 'aux', 'au', 'et',
        ];

        $parts = array_filter(explode(' ', $text));
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
}

