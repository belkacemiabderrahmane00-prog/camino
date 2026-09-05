<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WikimediaCommonsClient
{
    private string $endpoint;

    public function __construct(?string $endpoint = null)
    {
        $this->endpoint = $endpoint ?: 'https://commons.wikimedia.org/w/api.php';
    }

    /**
     * Récupère les métadonnées d'une image Commons (URLs, licence, auteur, etc.).
     *
     * @return array<string, mixed>|null
     */
    /**
     * Photo géolocalisée la plus pertinente autour d'un point (fichiers Commons avec coordonnées).
     * Préfère les fichiers dont le titre reprend des mots du nom du lieu, sinon le plus proche.
     *
     * @return array<string,mixed>|null même structure que getImageMeta() + 'filename', 'distance_m'
     */
    public function findNearbyImage(float $lat, float $lng, string $placeTitle, int $radiusMeters = 60, int $thumbWidth = 960): ?array
    {
        try {
            $response = Http::timeout(20)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'CAMINO/2.0 (contact: webmaster@camino.example)',
                    'Accept' => 'application/json',
                ])
                ->get($this->endpoint, [
                    'action' => 'query',
                    'generator' => 'geosearch',
                    'ggscoord' => sprintf('%.6f|%.6f', $lat, $lng),
                    'ggsradius' => max(10, min(500, $radiusMeters)),
                    'ggsnamespace' => 6,
                    'ggslimit' => 20,
                    'prop' => 'imageinfo|coordinates',
                    'iiprop' => 'url|extmetadata|mime|size',
                    'iiurlwidth' => $thumbWidth,
                    'format' => 'json',
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $pages = $response->json()['query']['pages'] ?? [];
        if ($pages === []) {
            return null;
        }

        $words = array_filter(array_map(
            fn ($w) => Str::ascii(mb_strtolower($w)),
            preg_split('/[^\p{L}\p{N}]+/u', $placeTitle) ?: []
        ), fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, ['eglise', 'musee', 'chateau', 'paris', 'saint', 'sainte', 'notre', 'dame', 'parc', 'jardin'], true));

        $best = null;
        $bestScore = -1.0;
        foreach ($pages as $page) {
            $info = $page['imageinfo'][0] ?? null;
            if (! $info || ! str_starts_with((string) ($info['mime'] ?? ''), 'image/') || ($info['mime'] ?? '') === 'image/svg+xml') {
                continue;
            }
            if (($info['width'] ?? 0) < 600) {
                continue;
            }
            $title = Str::ascii(mb_strtolower((string) ($page['title'] ?? '')));
            $matches = 0;
            foreach ($words as $w) {
                if (str_contains($title, $w)) {
                    $matches++;
                }
            }
            $dist = (float) ($page['coordinates'][0]['dist'] ?? $radiusMeters);
            $score = $matches * 10 + (1 - min($dist, $radiusMeters) / $radiusMeters) * 5
                + (str_contains($title, 'interior') || str_contains($title, 'interieur') ? -1 : 0)
                + (preg_match('/plaque|panneau|menu|ticket|plan_/', $title) ? -6 : 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['page' => $page, 'info' => $info, 'dist' => $dist];
            }
        }

        if (! $best) {
            return null;
        }

        $meta = $best['info']['extmetadata'] ?? [];

        return [
            'filename' => preg_replace('/^File:/', '', (string) $best['page']['title']),
            'distance_m' => $best['dist'],
            'image_url_original' => $best['info']['url'] ?? null,
            'image_url_thumb' => $best['info']['thumburl'] ?? null,
            'license' => $meta['LicenseShortName']['value'] ?? null,
            'author' => $this->stripHtml($meta['Artist']['value'] ?? null),
            'credit' => $this->stripHtml($meta['Credit']['value'] ?? null),
            'attribution_url' => $best['info']['descriptionurl'] ?? null,
            'raw' => $meta,
        ];
    }

    public function getImageMeta(string $filename, int $thumbWidth = 800): ?array
    {
        if ($filename === '') {
            return null;
        }

        $title = str_starts_with($filename, 'File:') ? $filename : 'File:' . $filename;

        try {
            $response = Http::timeout(20)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'CAMINO/1.0 (contact: webmaster@camino.example)',
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->get($this->endpoint, [
                    'action' => 'query',
                    'titles' => $title,
                    'prop' => 'imageinfo',
                    'iiprop' => 'url|extmetadata',
                    'iiurlwidth' => $thumbWidth,
                    'format' => 'json',
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();
        $pages = $data['query']['pages'] ?? [];
        $page = reset($pages);
        if (! $page || empty($page['imageinfo'][0])) {
            return null;
        }

        $info = $page['imageinfo'][0];
        $meta = $info['extmetadata'] ?? [];

        $license = $meta['LicenseShortName']['value'] ?? null;
        $author = $meta['Artist']['value'] ?? null;
        $credit = $meta['Credit']['value'] ?? null;
        $attributionUrl = $info['descriptionurl'] ?? null;

        return [
            'image_url_original' => $info['url'] ?? null,
            'image_url_thumb' => $info['thumburl'] ?? null,
            'license' => $license,
            'author' => $this->stripHtml($author),
            'credit' => $this->stripHtml($credit),
            'attribution_url' => $attributionUrl,
            'raw' => $meta,
        ];
    }

    /**
     * Liste des fichiers d'une catégorie Commons.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCategoryFiles(string $category, int $limit = 20): array
    {
        $category = trim($category);
        if ($category === '') {
            return [];
        }

        try {
            $response = Http::timeout(20)
                ->retry(1, 500)
                ->withHeaders([
                    'User-Agent' => 'CAMINO/1.0 (contact: webmaster@camino.example)',
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->get($this->endpoint, [
                    'action' => 'query',
                    'list' => 'categorymembers',
                    'cmtitle' => 'Category:' . $category,
                    'cmtype' => 'file',
                    'cmlimit' => $limit,
                    'format' => 'json',
                ]);
        } catch (\Throwable $e) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        $data = $response->json();
        $members = $data['query']['categorymembers'] ?? [];

        return is_array($members) ? $members : [];
    }

    /**
     * Choisit un fichier photo pertinent dans une catégorie (évite logos, cartes, blasons, SVG).
     *
     * @return array<string, mixed>|null
     */
    public function getBestImageFromCategory(string $category, int $thumbWidth = 800): ?array
    {
        $members = $this->listCategoryFiles($category, 30);
        if ($members === []) {
            return null;
        }

        $bannedPatterns = [
            'logo',
            'map',
            'carte',
            'plan',
            'coat of arms',
            'blason',
            'arms',
            'emblem',
            'flag',
            'icon',
            'pictogram',
            '.svg',
        ];

        foreach ($members as $m) {
            $title = $m['title'] ?? '';
            if ($title === '') {
                continue;
            }

            $lower = strtolower($title);
            $skip = false;
            foreach ($bannedPatterns as $pattern) {
                if (str_contains($lower, $pattern)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            // Garder surtout les JPG/PNG
            if (! preg_match('/\.(jpe?g|png)$/i', $title)) {
                continue;
            }

            $meta = $this->getImageMeta($title, $thumbWidth);
            if ($meta && ! empty($meta['image_url_original'])) {
                $meta['filename'] = $title;

                return $meta;
            }
        }

        return null;
    }

    private function stripHtml(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim(strip_tags($value));
    }
}

