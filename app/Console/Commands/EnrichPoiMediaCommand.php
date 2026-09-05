<?php

namespace App\Console\Commands;

use App\Models\Place;
use App\Models\PoiMedia;
use App\Models\PoiOsmMatch;
use App\Services\OsmOverpassClient;
use App\Services\WikidataClient;
use App\Services\WikimediaCommonsClient;
use App\Services\WikipediaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class EnrichPoiMediaCommand extends Command
{
    protected $signature = 'camino:enrich-poi-media
                            {--limit=50 : Nombre maximum de POI à traiter}
                            {--radius-km=0.4 : Rayon de recherche autour du POI pour Wikidata (en km)}
                            {--min-score=0.6 : Score minimal de matching Wikidata}
                            {--sleep-ms=250 : Pause entre chaque POI (en millisecondes)}
                            {--categories=musee,monument,lieu-culturel,parc-jardin : Slugs de catégories à traiter (séparés par des virgules)}
                            {--skip-overpass : Ne pas interroger Overpass/OSM (lent, faible rendement)}
                            {--city-fallback : Utiliser une photo de la ville quand aucune image du lieu n\'est trouvée}';

    protected $description = 'Enrichit les POI (places) avec des images open data via Wikidata/Wikimedia Commons, OSM et fallbacks Wikipedia/villes/catégories.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $radiusKm = (float) $this->option('radius-km');
        $minScore = (float) $this->option('min-score');
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $slugs = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('categories')))));
        $skipOverpass = (bool) $this->option('skip-overpass');
        $cityFallback = (bool) $this->option('city-fallback');

        $this->info("Enrichissement média pour {$limit} lieux sans image de couverture…");
        $this->info(sprintf(
            'Paramètres : radius-km=%.2f, min-score=%.2f, sleep-ms=%d',
            $radiusKm,
            $minScore,
            $sleepMs
        ));

        $overpass = new OsmOverpassClient();
        $wikidata = new WikidataClient();
        $commons = new WikimediaCommonsClient();
        $wikipedia = new WikipediaClient();

        $places = Place::query()
            ->where('status', 'approved')
            ->whereNull('cover_image_url')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereJsonContains('sources', ['datatourisme'])
            ->whereHas('category', function ($q) use ($slugs) {
                $q->whereIn('slug', $slugs);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($places->isEmpty()) {
            $this->info('Aucun lieu éligible trouvé.');

            return self::SUCCESS;
        }

        $this->info("Lieux candidats : {$places->count()}");

        foreach ($places as $place) {
            $this->line("→ {$place->id} - {$place->title}");

            if ($place->lat === null || $place->lng === null) {
                $this->line('   Coordonnées manquantes, POI ignoré.');
                continue;
            }

            $qid = null;
            $filename = null;

            // 1. Wikidata SPARQL around (source principale)
            $this->line('   Recherche Wikidata (SPARQL around)…');
            $bestNearby = $wikidata->findBestNearbyWithImage(
                (float) $place->lat,
                (float) $place->lng,
                $place->title,
                $radiusKm,
                $minScore
            );

            if ($bestNearby) {
                $qid = $bestNearby['qid'];
                $filename = $bestNearby['image_filename'];
                $this->line(sprintf(
                    '   ✓ Match Wikidata %s ("%s") score=%.3f (distance ~%d m).',
                    $qid,
                    $bestNearby['label'],
                    $bestNearby['score'],
                    (int) ($bestNearby['distance_m'] ?? 0)
                ));
            } else {
                $this->line('   Aucun match Wikidata SPARQL autour du point, fallback Overpass+Wikidata.');
            }

            // 2. Fallback : Overpass -> OSM -> Wikidata/Wikipedia
            if (! $filename && ! $skipOverpass) {
                $matches = $overpass->searchAround($place->lat, $place->lng, $place->title, (int) round($radiusKm * 1000));
                $best = $matches[0] ?? null;
                $score = $best['score'] ?? 0;
                if (! $best || $score < 0.5) {
                    $this->line(sprintf('   Aucun match OSM suffisamment fiable (score %.2f).', $score));
                } else {
                    $tags = $best['tags'] ?? [];
                    $wikidataTag = $tags['wikidata'] ?? null;
                    $wikipediaTag = $tags['wikipedia'] ?? null;

                    $osmMatch = PoiOsmMatch::updateOrCreate(
                        ['place_id' => $place->id],
                        [
                            'osm_type' => $best['osm_type'],
                            'osm_id' => $best['osm_id'],
                            'wikidata_qid' => $wikidataTag,
                            'match_score' => $best['score'],
                            'matched_at' => Carbon::now(),
                        ]
                    );

                    $this->line(sprintf(
                        '   Match OSM %s/%s score=%.3f, distance ~%d m.',
                        $osmMatch->osm_type,
                        $osmMatch->osm_id,
                        $osmMatch->match_score,
                        (int) ($best['distance_m'] ?? 0)
                    ));

                    $qid = $wikidataTag;

                    if (! $qid && $wikipediaTag) {
                        $this->line(sprintf('   Résolution du tag wikipedia=%s vers un QID…', $wikipediaTag));
                        $qid = $wikidata->getQidFromWikipediaTag($wikipediaTag);
                    }

                    // 2a. Wikidata : trouver P18 si QID présent
                    if ($qid) {
                        $filename = $wikidata->getMainImageFilename($qid);
                        if (! $filename) {
                            $this->line("   Aucun P18 (image principale) trouvé pour {$qid}.");
                        }
                    }

                    // 2b. Fallback : tag wikimedia_commons=* sur l'objet OSM
                    if (! $filename && ! empty($tags['wikimedia_commons'])) {
                        $filename = $tags['wikimedia_commons'];
                        $this->line('   Utilisation du tag wikimedia_commons=* de OSM.');
                    }

                    // 2c. Fallback : tag image=* pointant vers Commons
                    if (! $filename && ! empty($tags['image']) && is_string($tags['image']) && str_contains($tags['image'], 'wikimedia.org')) {
                        $url = $tags['image'];
                        $path = parse_url($url, PHP_URL_PATH) ?? $url;
                        $filename = basename($path);
                        $this->line('   Utilisation du tag image=* provenant de Wikimedia.');
                    }
                }
            }

            // 2bis. Photo Commons géolocalisée autour du point (rendement élevé pour monuments, parcs, musées).
            if (! $filename) {
                $this->line('   Recherche d\'une photo Commons géolocalisée (rayon 60 m)…');
                $nearby = $commons->findNearbyImage((float) $place->lat, (float) $place->lng, $place->title, 60, 960);
                if ($nearby && ! empty($nearby['image_url_original'])) {
                    PoiMedia::where('place_id', $place->id)->where('is_cover', true)->update(['is_cover' => false]);
                    PoiMedia::create([
                        'place_id' => $place->id,
                        'source' => 'wikimedia_commons',
                        'title' => $nearby['filename'],
                        'image_url_original' => $nearby['image_url_original'],
                        'image_url_thumb' => $nearby['image_url_thumb'] ?? null,
                        'license' => $nearby['license'] ?? null,
                        'author' => $nearby['author'] ?? null,
                        'attribution_url' => $nearby['attribution_url'] ?? null,
                        'is_cover' => true,
                        'extra' => ['credit' => $nearby['credit'] ?? null, 'geosearch_distance_m' => $nearby['distance_m']],
                    ]);
                    $place->cover_image_url = $nearby['image_url_thumb'] ?? $nearby['image_url_original'];
                    $place->cover_image_source = 'wikimedia_commons';
                    $place->cover_image_license = $nearby['license'] ?? null;
                    $place->cover_image_author = $nearby['author'] ?? null;
                    $place->cover_image_attribution = $nearby['credit'] ?? null;
                    $place->cover_image_page_url = $nearby['attribution_url'] ?? null;
                    $place->cover_image_is_fallback = false;
                    $place->cover_image_fallback_reason = 'commons_geosearch';
                    $place->save();
                    $this->line(sprintf('   ✓ Photo géolocalisée "%s" (~%d m).', $nearby['filename'], (int) $nearby['distance_m']));
                    if ($sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }

                    continue;
                }
                $this->line('   Aucune photo géolocalisée exploitable.');
            }

            // 3+. Sauvegarder dans poi_media + mettre à jour cover_image_url
            PoiMedia::where('place_id', $place->id)
                ->where('is_cover', true)
                ->update(['is_cover' => false]);

            $fallbackReason = null;

            if ($filename) {
                // Wikimedia Commons : métadonnées
                $meta = $commons->getImageMeta($filename, 800);
                if (! $meta || empty($meta['image_url_original'])) {
                    $this->line("   Impossible de récupérer les métadonnées Commons pour {$filename}.");
                    $filename = null;
                } else {
                    PoiMedia::create([
                        'place_id' => $place->id,
                        'source' => 'wikimedia_commons',
                        'title' => $meta['title'] ?? null,
                        'image_url_original' => $meta['image_url_original'],
                        'image_url_thumb' => $meta['image_url_thumb'] ?? null,
                        'license' => $meta['license'] ?? null,
                        'author' => $meta['author'] ?? null,
                        'attribution_url' => $meta['attribution_url'] ?? null,
                        'is_cover' => true,
                        'extra' => [
                            'credit' => $meta['credit'] ?? null,
                        ],
                    ]);

                    $place->cover_image_url = $meta['image_url_thumb'] ?? $meta['image_url_original'];
                    $place->cover_image_source = 'wikimedia_commons';
                    $place->cover_image_license = $meta['license'] ?? null;
                    $place->cover_image_author = $meta['author'] ?? null;
                    $place->cover_image_attribution = $meta['credit'] ?? null;
                    $place->cover_image_page_url = $meta['attribution_url'] ?? null;
                    if ($qid) {
                        $place->wikidata_qid = $qid;
                    }

                    $this->line('   ✓ Image de couverture ajoutée depuis Wikimedia Commons (avec métadonnées licence/auteur).');
                }
            }

            // 4. Fallback Wikipedia thumbnail (si QID mais pas d'image Commons)
            if (! $place->cover_image_url && $qid) {
                $this->line('   Aucun fichier Commons exploitable, tentative thumbnail Wikipedia…');
                $titleFr = $wikidata->getFrenchWikipediaTitle($qid);
                if ($titleFr) {
                    $thumb = $wikipedia->getThumbnailForTitle($titleFr, 'fr');
                    if ($thumb && ! empty($thumb['thumbnail_url'])) {
                        PoiMedia::create([
                            'place_id' => $place->id,
                            'source' => 'wikipedia',
                            'title' => $thumb['title'] ?? $titleFr,
                            'image_url_original' => $thumb['thumbnail_url'],
                            'image_url_thumb' => $thumb['thumbnail_url'],
                            'license' => null,
                            'author' => null,
                            'attribution_url' => $thumb['page_url'] ?? null,
                            'is_cover' => true,
                            'extra' => [
                                'attribution' => null,
                            ],
                        ]);

                        $place->cover_image_url = $thumb['thumbnail_url'];
                        $place->cover_image_source = 'wikipedia';
                        $place->cover_image_license = null;
                        $place->cover_image_author = null;
                        $place->cover_image_attribution = null;
                        $place->cover_image_page_url = $thumb['page_url'] ?? null;
                        $fallbackReason = 'wikipedia_thumbnail';

                        $this->line('   ✓ Thumbnail Wikipedia utilisé comme image de couverture.');
                    } else {
                        $this->line('   Aucun thumbnail Wikipedia disponible.');
                    }
                } else {
                    $this->line('   Aucun titre Wikipedia FR trouvé pour ce QID.');
                }
            }

            // 5. Fallback Commons category via P373
            if (! $place->cover_image_url && $qid) {
                $this->line('   Tentative via catégorie Commons (P373)…');
                $category = $wikidata->getCommonsCategory($qid);
                if ($category) {
                    $metaCat = $commons->getBestImageFromCategory($category, 800);
                    if ($metaCat && ! empty($metaCat['image_url_original'])) {
                        PoiMedia::create([
                            'place_id' => $place->id,
                            'source' => 'wikimedia_commons',
                            'title' => $metaCat['title'] ?? null,
                            'image_url_original' => $metaCat['image_url_original'],
                            'image_url_thumb' => $metaCat['image_url_thumb'] ?? null,
                            'license' => $metaCat['license'] ?? null,
                            'author' => $metaCat['author'] ?? null,
                            'attribution_url' => $metaCat['attribution_url'] ?? null,
                            'is_cover' => true,
                            'extra' => [
                                'credit' => $metaCat['credit'] ?? null,
                                'category' => $category,
                            ],
                        ]);

                        $place->cover_image_url = $metaCat['image_url_thumb'] ?? $metaCat['image_url_original'];
                        $place->cover_image_source = 'wikimedia_commons';
                        $place->cover_image_license = $metaCat['license'] ?? null;
                        $place->cover_image_author = $metaCat['author'] ?? null;
                        $place->cover_image_attribution = $metaCat['credit'] ?? null;
                        $place->cover_image_page_url = $metaCat['attribution_url'] ?? null;
                        $fallbackReason = 'commons_category';

                        $this->line('   ✓ Image trouvée via catégorie Commons.');
                    } else {
                        $this->line('   Aucune image pertinente trouvée dans la catégorie Commons.');
                    }
                } else {
                    $this->line('   Pas de P373 (catégorie Commons) pour ce QID.');
                }
            }

            // 6. Fallback image de la ville (Wikipedia), uniquement sur demande explicite
            if ($cityFallback && ! $place->cover_image_url && $place->address) {
                $city = $this->extractCityFromAddress($place->address);
                if ($city) {
                    $this->line(sprintf('   Tentative image de ville via Wikipedia pour "%s"…', $city));
                    $thumbCity = $wikipedia->getThumbnailForTitle($city, 'fr');
                    if ($thumbCity && ! empty($thumbCity['thumbnail_url'])) {
                        PoiMedia::create([
                            'place_id' => $place->id,
                            'source' => 'wikipedia_city',
                            'title' => $thumbCity['title'] ?? $city,
                            'image_url_original' => $thumbCity['thumbnail_url'],
                            'image_url_thumb' => $thumbCity['thumbnail_url'],
                            'license' => null,
                            'author' => null,
                            'attribution_url' => $thumbCity['page_url'] ?? null,
                            'is_cover' => true,
                            'extra' => [
                                'city' => $city,
                            ],
                        ]);

                        $place->cover_image_url = $thumbCity['thumbnail_url'];
                        $place->cover_image_source = 'wikipedia_city';
                        $place->cover_image_license = null;
                        $place->cover_image_author = null;
                        $place->cover_image_attribution = null;
                        $place->cover_image_page_url = $thumbCity['page_url'] ?? null;
                        $fallbackReason = 'city';

                        $this->line('   ✓ Image de ville utilisée comme fallback.');
                    } else {
                        $this->line('   Aucune image de ville trouvée via Wikipedia.');
                    }
                }
            }

            // 7. Aucun fallback fichier : sans image, les vues affichent un visuel de catégorie.
            if (! $place->cover_image_url) {
                $this->line('   Aucune image trouvée en ligne : visuel de catégorie affiché côté vues.');
            }

            if ($place->cover_image_url) {
                $place->cover_image_is_fallback = $fallbackReason !== null;
                $place->cover_image_fallback_reason = $fallbackReason;
            }

            $place->save();

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->info('Enrichissement terminé.');

        return self::SUCCESS;
    }

    private function extractCityFromAddress(string $address): ?string
    {
        // Heuristique:
        // - prendre la dernière partie après une virgule
        // - retirer un éventuel code postal en début ("95290 L'Isle-Adam" -> "L'Isle-Adam")
        // - retirer un éventuel suffixe "France"
        $parts = array_map('trim', explode(',', $address));
        $segment = end($parts) ?: '';

        if ($segment === '') {
            return null;
        }

        // Retirer suffixe pays
        $segment = preg_replace('/\b(France|FRANCE|FR)\b/u', '', $segment) ?? $segment;
        $segment = trim($segment);

        // Retirer un code postal numérique en début (4 ou 5 chiffres)
        $tokens = preg_split('/\s+/', $segment) ?: [];
        if (isset($tokens[0]) && preg_match('/^\d{4,5}$/', $tokens[0])) {
            array_shift($tokens);
        }

        $city = trim(implode(' ', $tokens));

        return $city !== '' ? $city : null;
    }
}

