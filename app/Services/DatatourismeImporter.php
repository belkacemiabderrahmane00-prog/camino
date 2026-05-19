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
            $fr = collect($label)->first(fn ($v) => (is_array($v) && ($v['@language'] ?? '') === 'fr') || is_string($v));
            return is_array($fr) ? ($fr['@value'] ?? null) : $fr;
        }

        return null;
    }

    private function extractLat(array $data): ?float
    {
        $geo = $data['schema:geo'] ?? $data['geo'] ?? $data['geo:lat'] ?? null;
        if (is_numeric($geo)) {
            return (float) $geo;
        }
        if (is_array($geo)) {
            $lat = $geo['latitude'] ?? $geo['geo:lat'] ?? $geo['@value'] ?? null;
            return $lat !== null ? (float) $lat : null;
        }

        $lat = $data['latitude'] ?? $data['lat'] ?? null;

        return $lat !== null ? (float) $lat : null;
    }

    private function extractLng(array $data): ?float
    {
        $geo = $data['schema:geo'] ?? $data['geo'] ?? $data['geo:long'] ?? null;
        if (is_numeric($geo)) {
            return (float) $geo;
        }
        if (is_array($geo)) {
            $lng = $geo['longitude'] ?? $geo['geo:long'] ?? $geo['@value'] ?? null;
            return $lng !== null ? (float) $lng : null;
        }

        $lng = $data['longitude'] ?? $data['lng'] ?? null;

        return $lng !== null ? (float) $lng : null;
    }

    private function extractAddress(array $data): ?string
    {
        $addr = $data['schema:address'] ?? $data['address'] ?? $data['isLocatedAt'] ?? null;
        if (is_string($addr)) {
            return $addr;
        }
        if (is_array($addr)) {
            $parts = array_filter([
                $addr['streetAddress'] ?? $addr['schema:streetAddress'] ?? null,
                ($addr['postalCode'] ?? $addr['schema:postalCode'] ?? null) . ' ' . ($addr['addressLocality'] ?? $addr['schema:addressLocality'] ?? null),
            ]);

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
        if (is_array($desc) && isset($desc['@value'])) {
            return $desc['@value'];
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
                if ($cat = Category::where('slug', 'parc-jardin')->first()) {
                    return $cat->id;
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
                $cat = Category::where('slug', $slug)->first();

                return $cat?->id;
            }
        }

        return Category::where('slug', 'lieu-culturel')->first()?->id;
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
            if ($label) {
                $tags[] = is_string($label) ? $label : ($label['@value'] ?? '');
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
