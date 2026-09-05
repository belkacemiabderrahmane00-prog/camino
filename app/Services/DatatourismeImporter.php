<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Place;
use Illuminate\Support\Str;
use ZipArchive;

class DatatourismeImporter
{
    /** Mapping type DATAtourisme → slug catégorie CAMINO (aligné avec CategorySeeder) */
    private const TYPE_TO_CATEGORY = [
        'CulturalSite' => 'lieu-culturel',
        'NaturalHeritage' => 'parc-jardin',
        'SportsAndLeisurePlace' => 'parc-jardin',
        'FoodEstablishment' => 'restauration',
        'TouristInformationCenter' => 'lieu-culturel',
        'CulturalEvent' => 'evenement-culturel',
        'WalkingTour' => 'itineraire',
        'CyclingTour' => 'itineraire',
        'Visit' => 'musee',
        'Practice' => 'lieu-culturel',
        'PointOfInterest' => 'lieu-culturel',
    ];

    /** @var array<string,int|null> cache slug => id (évite une requête par fichier) */
    private array $categoryCache = [];

    public function __construct(
        private readonly string $zipPath,
        private readonly bool $dryRun = false,
    ) {}

    public function run(): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        $tempDir = sys_get_temp_dir() . '/camino-datatourisme-' . uniqid();
        if (! $this->extractZip($tempDir)) {
            return ['errors' => 1, 'message' => 'Impossible d\'extraire l\'archive ZIP.'];
        }

        $objectsDir = $tempDir . '/objects';
        if (! is_dir($objectsDir)) {
            $objectsDir = $tempDir;
        }

        $jsonFiles = $this->findJsonFiles($objectsDir);
        $total = count($jsonFiles);

        foreach ($jsonFiles as $i => $filePath) {
            $result = $this->importFile($filePath);
            if ($result === 'created') {
                $stats['created']++;
            } elseif ($result === 'updated') {
                $stats['updated']++;
            } elseif ($result === 'skipped') {
                $stats['skipped']++;
            } else {
                $stats['errors']++;
            }
        }

        $this->removeDir($tempDir);

        return $stats;
    }

    private function extractZip(string $dest): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($this->zipPath, ZipArchive::RDONLY) !== true) {
            return false;
        }
        $zip->extractTo($dest);
        $zip->close();

        return true;
    }

    /** @return array<string> */
    private function findJsonFiles(string $dir): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with(strtolower($file->getFilename()), '.json')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function importFile(string $filePath): string
    {
        $raw = file_get_contents($filePath);
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return 'error';
        }

        $externalId = $this->extractId($data);
        $title = $this->extractTitle($data);
        $lat = $this->extractLat($data);
        $lng = $this->extractLng($data);

        if (empty($title) || ! $lat || ! $lng) {
            return 'skipped';
        }

        $address = $this->extractAddress($data);
        $description = $this->extractDescription($data);
        $categoryId = $this->resolveCategory($data);
        $tags = $this->extractTags($data);
        $coverUrl = $this->extractCoverImage($data);
        $isFree = $this->extractIsFree($data);
        $priceLevel = $this->extractPriceLevel($data);

        $payload = [
            'title' => Str::limit($title, 255),
            'slug' => Str::slug($title),
            'description' => $description ? Str::limit($description, 2000) : null,
            'category_id' => $categoryId,
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
            'address' => $address ? Str::limit($address, 255) : null,
            'status' => 'approved',
            'is_free' => $isFree,
            'price_level' => $priceLevel,
            'visit_duration_min' => 90,
            'tags' => $tags,
            'cover_image_url' => $coverUrl,
            'gallery' => [],
            'sources' => ['datatourisme'],
            'external_id' => $externalId,
        ];

        if ($this->dryRun) {
            return 'skipped';
        }

        $place = Place::query()->where('external_id', $externalId)->first();

        if ($place) {
            $place->update($payload);

            return 'updated';
        }

        Place::create($payload);

        return 'created';
    }

    private function extractId(array $data): string
    {
        $id = $data['@id'] ?? $data['dc:identifier'] ?? $data['identifier'] ?? null;
        if (is_string($id)) {
            return $id;
        }
        if (is_array($id) && isset($id['@value'])) {
            return (string) $id['@value'];
        }

        return md5(json_encode($data));
    }

    private function extractTitle(array $data): ?string
    {
        $label = $data['rdfs:label'] ?? $data['name'] ?? $data['schema:name'] ?? null;
        if (is_string($label)) {
            return $label;
        }
        if (is_array($label)) {
            if (isset($label['fr'])) {
                $fr = $label['fr'];
                return is_array($fr) ? ($fr[0] ?? null) : $fr;
            }
            $fr = collect($label)->first(fn ($v) => (is_array($v) && ($v['@language'] ?? '') === 'fr') || is_string($v));
            return is_array($fr) ? ($fr['@value'] ?? ($fr[0] ?? null)) : $fr;
        }

        return null;
    }

    private function extractGeo(array $data): ?array
    {
        $geo = $data['schema:geo'] ?? $data['geo'] ?? null;
        if (is_array($geo) && isset($geo['schema:latitude'])) {
            return $geo;
        }

        $locations = $data['isLocatedAt'] ?? [];
        if (is_array($locations)) {
            $loc = isset($locations[0]) ? $locations[0] : $locations;
            $geo = $loc['schema:geo'] ?? $loc['geo'] ?? null;
            if (is_array($geo)) {
                return $geo;
            }
        }

        return null;
    }

    private function extractLat(array $data): ?float
    {
        $geo = $this->extractGeo($data);
        if ($geo) {
            $lat = $geo['schema:latitude'] ?? $geo['latitude'] ?? $geo['geo:lat'] ?? null;
            return $lat !== null ? (float) $lat : null;
        }

        $lat = $data['latitude'] ?? $data['lat'] ?? null;

        return $lat !== null ? (float) $lat : null;
    }

    private function extractLng(array $data): ?float
    {
        $geo = $this->extractGeo($data);
        if ($geo) {
            $lng = $geo['schema:longitude'] ?? $geo['longitude'] ?? $geo['geo:long'] ?? null;
            return $lng !== null ? (float) $lng : null;
        }

        $lng = $data['longitude'] ?? $data['lng'] ?? null;

        return $lng !== null ? (float) $lng : null;
    }

    private function extractAddress(array $data): ?string
    {
        $addr = $data['schema:address'] ?? $data['address'] ?? null;

        if (! $addr) {
            $locations = $data['isLocatedAt'] ?? [];
            $loc = is_array($locations) ? ($locations[0] ?? $locations) : null;
            if (is_array($loc)) {
                $addrList = $loc['schema:address'] ?? $loc['address'] ?? null;
                $addr = is_array($addrList) ? ($addrList[0] ?? $addrList) : $addrList;
            }
        }

        if (is_string($addr)) {
            return $addr;
        }
        if (is_array($addr)) {
            $street = $addr['schema:streetAddress'] ?? $addr['streetAddress'] ?? null;
            if (is_array($street)) {
                $street = $street[0] ?? null;
            }
            $postal = $addr['schema:postalCode'] ?? $addr['postalCode'] ?? '';
            $city = $addr['schema:addressLocality'] ?? $addr['addressLocality'] ?? '';
            $parts = array_filter([$street, trim("$postal $city")]);

            return implode(', ', $parts) ?: null;
        }

        return null;
    }

    private function extractDescription(array $data): ?string
    {
        $desc = $data['rdfs:comment'] ?? $data['schema:description'] ?? $data['description'] ?? null;
        if (is_string($desc)) {
            return $desc;
        }
        if (is_array($desc)) {
            if (isset($desc['fr'])) {
                $fr = $desc['fr'];
                return is_array($fr) ? ($fr[0] ?? null) : $fr;
            }
            if (isset($desc['@value'])) {
                return $desc['@value'];
            }
        }

        return null;
    }

    private function resolveCategory(array $data): ?int
    {
        // Heuristique simple basée sur le titre pour repérer les parcs / jardins,
        // afin d'éviter qu'ils tombent dans "Monument" ou "Lieu culturel".
        $title = mb_strtolower($this->extractTitle($data) ?? '');
        if ($title !== '') {
            if (
                str_contains($title, 'parc') ||
                str_contains($title, 'jardin') ||
                str_contains($title, 'square') ||
                str_contains($title, 'bois') ||
                str_contains($title, 'forêt') ||
                str_contains($title, 'foret')
            ) {
                if ($id = $this->categoryId('parc-jardin')) {
                    return $id;
                }
            }
        }

        $types = $data['@type'] ?? [];
        if (is_string($types)) {
            $types = [$types];
        }
        foreach ($types as $type) {
            $str = (string) $type;
            $short = str_contains($str, '#') ? substr($str, strrpos($str, '#') + 1) : class_basename($str);
            $slug = self::TYPE_TO_CATEGORY[$short] ?? null;
            if ($slug) {
                return $this->categoryId($slug);
            }
        }

        return $this->categoryId('lieu-culturel');
    }

    private function categoryId(string $slug): ?int
    {
        if (! array_key_exists($slug, $this->categoryCache)) {
            $this->categoryCache[$slug] = Category::where('slug', $slug)->value('id');
        }

        return $this->categoryCache[$slug];
    }

    private function extractTags(array $data): array
    {
        $themes = $data['hasTheme'] ?? $data['theme'] ?? [];
        if (! is_array($themes)) {
            return [];
        }
        $tags = [];
        foreach ($themes as $t) {
            $label = is_array($t) ? ($t['rdfs:label'] ?? $t['name'] ?? null) : $t;
            if (is_string($label)) {
                $tags[] = $label;
            } elseif (is_array($label)) {
                if (isset($label['fr'])) {
                    $fr = $label['fr'];
                    $tags[] = is_array($fr) ? ($fr[0] ?? '') : $fr;
                } elseif (isset($label['@value'])) {
                    $tags[] = $label['@value'];
                }
            }
        }

        return array_filter(array_slice($tags, 0, 10));
    }

    private function extractCoverImage(array $data): ?string
    {
        $rep = $data['hasMainRepresentation'] ?? $data['image'] ?? $data['schema:image'] ?? null;
        if (is_string($rep)) {
            return $rep;
        }
        if (is_array($rep)) {
            return $rep['ebucore:hasRelatedResource'] ?? $rep['url'] ?? $rep['@id'] ?? null;
        }

        return null;
    }

    private function extractIsFree(array $data): bool
    {
        $price = $data['schema:price'] ?? $data['hasPricingMode'] ?? null;
        if (is_string($price) && stripos($price, 'gratuit') !== false) {
            return true;
        }

        return false;
    }

    private function extractPriceLevel(array $data): ?int
    {
        $price = $data['schema:price'] ?? $data['price'] ?? null;
        if ($price === null || $price === '' || stripos((string) $price, 'gratuit') !== false) {
            return null;
        }
        if (preg_match('/€+/', (string) $price, $m)) {
            return strlen($m[0]);
        }

        return 2;
    }

    private function removeDir(string $dir): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
