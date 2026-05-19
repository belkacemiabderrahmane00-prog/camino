<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WikipediaClient
{
    /**
     * Retourne les infos de résumé de page pour un titre donné (lang par défaut: fr).
     *
     * @return array<string, mixed>|null
     */
    public function getPageSummary(string $title, string $lang = 'fr'): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $lang = $lang ?: 'fr';
        $url = sprintf('https://%s.wikipedia.org/api/rest_v1/page/summary/%s', $lang, rawurlencode($title));

        try {
            $response = Http::timeout(15)
                ->retry(1, 500)
                ->withHeaders([
                    'User-Agent' => 'CAMINO/1.0 (contact: webmaster@camino.example)',
                    'Accept' => 'application/json',
                ])
                ->get($url);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Récupère le thumbnail et l'URL de page pour un titre.
     *
     * @return array<string, mixed>|null
     */
    public function getThumbnailForTitle(string $title, string $lang = 'fr'): ?array
    {
        $data = $this->getPageSummary($title, $lang);
        if (! $data) {
            return null;
        }

        $thumb = $data['thumbnail']['source'] ?? null;
        if (! $thumb) {
            return null;
        }

        $pageUrl = $data['content_urls']['desktop']['page'] ?? $data['content_urls']['mobile']['page'] ?? null;

        return [
            'thumbnail_url' => $thumb,
            'page_url' => $pageUrl,
            'title' => $data['title'] ?? $title,
        ];
    }
}

