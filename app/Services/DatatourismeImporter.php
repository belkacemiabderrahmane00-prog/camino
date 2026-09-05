<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Place;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Ingestion du flux DATAtourisme (JSON-LD, une archive ZIP contenant un fichier par objet).
 *
 * Principes :
 *  - un objet porte plusieurs @type (ex. Museum + CulturalSite + PointOfInterest) : on classe
 *    selon le type le plus spécifique, dans l'ordre de TYPE_TO_CATEGORY ;
 *  - les objets hors sujet pour une app culturelle (hôtels, piscines, stades, boutiques…)
 *    sont importés en statut "hidden" pour pouvoir être réactivés sans ré-import ;
 *  - l'écriture se fait par upsert en lots sur external_id (unique).
 */
class DatatourismeImporter
{
    /**
     * Mapping type DATAtourisme → slug catégorie CAMINO, du plus spécifique au plus générique.
     * Le premier type de l'objet trouvé dans cette liste (dans cet ordre) l'emporte.
     */
    private const TYPE_TO_CATEGORY = [
        // Musées
        'Museum' => 'musee',
        'InterpretationCentre' => 'musee',
        'VivariumAquarium' => 'musee',
        // Monuments & patrimoine bâti
        'Castle' => 'monument',
        'FortifiedCastle' => 'monument',
        'Palace' => 'monument',
        'Cathedral' => 'monument',
        'Basilica' => 'monument',
        'Abbey' => 'monument',
        'Monastery' => 'monument',
        'Convent' => 'monument',
        'Cloister' => 'monument',
        'Collegiate' => 'monument',
        'Church' => 'monument',
        'Chapel' => 'monument',
        'Synagogue' => 'monument',
        'Mosque' => 'monument',
        'Temple' => 'monument',
        'BuddhistTemple' => 'monument',
        'ReligiousSite' => 'monument',
        'RemarkableBuilding' => 'monument',
        'TechnicalHeritage' => 'monument',
        'IndustrialSite' => 'monument',
        'ArcheologicalSite' => 'monument',
        'Ruins' => 'monument',
        'RemembranceSite' => 'monument',
        'DefenceSite' => 'monument',
        'Fort' => 'monument',
        'Tower' => 'monument',
        'Bridge' => 'monument',
        'Aqueduct' => 'monument',
        'Lock' => 'monument',
        'Canal' => 'monument',
        'LevyOrDike' => 'monument',
        'Mill' => 'monument',
        'Bastide' => 'monument',
        'Commanderie' => 'monument',
        'MilitaryCemetery' => 'monument',
        'CivilCemetery' => 'monument',
        'CityHeritage' => 'monument',
        // Parcs, jardins, nature
        'ParkAndGarden' => 'parc-jardin',
        'NaturalHeritage' => 'parc-jardin',
        'ZooAnimalPark' => 'parc-jardin',
        'ThemePark' => 'parc-jardin',
        'AdventurePark' => 'parc-jardin',
        'TeachingFarm' => 'parc-jardin',
        'Farm' => 'parc-jardin',
        'Beach' => 'parc-jardin',
        // Itinéraires & visites guidées
        'WalkingTour' => 'itineraire',
        'CyclingTour' => 'itineraire',
        'HorseTour' => 'itineraire',
        'RoadTour' => 'itineraire',
        'FluvialTour' => 'itineraire',
        'EducationalTrail' => 'itineraire',
        'SightseeingBoat' => 'itineraire',
        'TouristTrain' => 'itineraire',
        'Tour' => 'itineraire',
        'Visit' => 'itineraire',
        // Événements
        'CulturalEvent' => 'evenement-culturel',
        'Exhibition' => 'evenement-culturel',
        'ShowEvent' => 'evenement-culturel',
        'TheaterEvent' => 'evenement-culturel',
        'Concert' => 'evenement-culturel',
        'Festival' => 'evenement-culturel',
        'VisualArtsEvent' => 'evenement-culturel',
        'ScreeningEvent' => 'evenement-culturel',
        'OpenDay' => 'evenement-culturel',
        'LocalAnimation' => 'evenement-culturel',
        'SocialEvent' => 'evenement-culturel',
        // Restauration
        'Restaurant' => 'restauration',
        'GourmetRestaurant' => 'restauration',
        'BistroOrWineBar' => 'restauration',
        'BrasserieOrTavern' => 'restauration',
        'BarOrPub' => 'restauration',
        'CafeOrTeahouse' => 'restauration',
        'FastFoodRestaurant' => 'restauration',
        'IceCreamShop' => 'restauration',
        'Bakery' => 'restauration',
        'CoveredMarket' => 'restauration',
        'Cellar' => 'restauration',
        'TastingProvider' => 'restauration',
        'FoodEstablishment' => 'restauration',
        // Lieux culturels (scènes, bibliothèques, galeries…)
        'ArtGalleryOrExhibitionGallery' => 'lieu-culturel',
        'Library' => 'lieu-culturel',
        'Theater' => 'lieu-culturel',
        'Opera' => 'lieu-culturel',
        'Cinema' => 'lieu-culturel',
        'Cabaret' => 'lieu-culturel',
        'CircusPlace' => 'lieu-culturel',
        'TouristInformationCenter' => 'lieu-culturel',
        'LocalTouristOffice' => 'lieu-culturel',
        'CulturalSite' => 'lieu-culturel',
        'EntertainmentAndEvent' => 'evenement-culturel',
    ];

    /**
     * Types hors sujet pour CAMINO : importés en statut "hidden".
     * Un objet qui porte aussi un type culturel (ex. château-hôtel) reste visible.
     */
    private const HIDDEN_TYPES = [
        'Accommodation', 'Hotel', 'HotelTrade', 'HotelRestaurant', 'Rental', 'NonHousingRealEstateRental',
        'Store', 'Product', 'LocalProductsShop', 'CraftsmanShop',
        'EquestrianCenter', 'SwimmingPool', 'Stadium', 'GolfCourse', 'MiniGolf', 'TennisComplex', 'SquashCourt',
        'ClimbingWall', 'FitnessCenter', 'FitnessPath', 'Gymnasium', 'IceSkatingRink', 'BowlingAlley',
        'TrackRollerOrSkateBoard', 'Velodrome', 'RacingCircuit', 'Racetrack', 'NauticalCentre', 'Marina',
        'RiverPort', 'Airfield', 'NightClub', 'Casino', 'Hammam', 'BalneotherapyCentre', 'LeisureComplex',
        'PlayArea', 'BusinessPlace', 'ConventionCentre', 'MultiPurposeRoomOrCommunityRoom',
        'Transport', 'Transporter', 'ServiceProvider', 'IncomingTravelAgency', 'TourOperatorOrTravelAgency',
        'SportsEvent', 'SportsCompetition', 'SaleEvent', 'FairOrShow', 'Conference', 'Game',
        'SportsAndLeisurePlace', 'Practice', 'District',
    ];

    /** Types dont les lieux sont gratuits par défaut quand aucune offre tarifaire n'est fournie. */
    private const FREE_BY_DEFAULT_TYPES = [
        'ParkAndGarden', 'NaturalHeritage', 'Beach', 'Church', 'Chapel', 'Cathedral', 'Basilica', 'Synagogue',
        'Mosque', 'Temple', 'BuddhistTemple', 'ReligiousSite', 'MilitaryCemetery', 'CivilCemetery', 'Bridge',
        'Aqueduct', 'Lock', 'Canal', 'LevyOrDike', 'RemembranceSite', 'CityHeritage', 'Library',
        'TouristInformationCenter', 'LocalTouristOffice', 'CoveredMarket',
    ];

    /** Durée de visite indicative (minutes) par catégorie. */
    private const VISIT_MINUTES = [
        'musee' => 120,
        'monument' => 45,
        'parc-jardin' => 60,
        'street-art' => 45,
        'lieu-culturel' => 60,
        'restauration' => 90,
        'itineraire' => 120,
        'evenement-culturel' => 120,
    ];

    private const UPSERT_CHUNK = 500;

    /** Colonnes mises à jour lorsqu'un lieu existe déjà (les enrichissements média Wikimedia sont conservés). */
    private const UPSERT_UPDATE_COLUMNS = [
        'title', 'slug', 'description', 'category_id', 'lat', 'lng', 'address', 'status',
        'is_free', 'price_level', 'visit_duration_min', 'event_start_at', 'event_end_at', 'tags', 'sources', 'updated_at',
    ];

    /** Colonnes image, mises à jour uniquement pour les objets qui ont une image dans le flux. */
    private const COVER_COLUMNS = [
        'cover_image_url', 'cover_image_source', 'cover_image_license',
        'cover_image_author', 'cover_image_attribution', 'cover_image_page_url',
        'cover_image_is_fallback', 'cover_image_fallback_reason',
    ];

    /** @var array<string,int|null> cache slug => id (évite une requête par fichier) */
    private array $categoryCache = [];

    public function __construct(
        private readonly string $zipPath,
        private readonly bool $dryRun = false,
    ) {}

    /**
     * @return array{created:int,updated:int,skipped:int,errors:int,hidden:int}|array{errors:int,message:string}
     */
    public function run(): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'hidden' => 0];

        $tempDir = sys_get_temp_dir() . '/camino-datatourisme-' . uniqid();
        if (! $this->extractZip($tempDir)) {
            return ['errors' => 1, 'message' => 'Impossible d\'extraire l\'archive ZIP.'];
        }

        $objectsDir = $tempDir . '/objects';
        if (! is_dir($objectsDir)) {
            $objectsDir = $tempDir;
        }

        $jsonFiles = $this->findJsonFiles($objectsDir);

        // Dédoublonnage par external_id (un même objet peut apparaître plusieurs fois dans le flux).
        $rows = [];
        foreach ($jsonFiles as $filePath) {
            $result = $this->importFile($filePath);
            if (is_array($result)) {
                $rows[$result['external_id']] = $result;
                if ($result['status'] === 'hidden') {
                    $stats['hidden']++;
                }
            } elseif ($result === 'skipped') {
                $stats['skipped']++;
            } else {
                $stats['errors']++;
            }
        }

        $this->removeDir($tempDir);

        if ($this->dryRun) {
            $stats['skipped'] += count($rows);

            return $stats;
        }

        // 1. Upsert des colonnes de base (1 requête par lot au lieu de 2 requêtes par lieu).
        $coreColumns = array_diff(array_keys(reset($rows) ?: []), self::COVER_COLUMNS);
        foreach (array_chunk(array_values($rows), self::UPSERT_CHUNK) as $chunk) {
            $ids = array_column($chunk, 'external_id');
            $existing = Place::query()->whereIn('external_id', $ids)->count();

            $core = array_map(fn (array $r) => array_intersect_key($r, array_flip($coreColumns)), $chunk);
            Place::query()->upsert($core, ['external_id'], self::UPSERT_UPDATE_COLUMNS);

            $stats['updated'] += $existing;
            $stats['created'] += count($chunk) - $existing;
        }

        // 2. Images fournies par le flux : elles priment sur les fallbacks, sans écraser les autres lieux.
        $withCover = array_values(array_filter($rows, fn (array $r) => $r['cover_image_url'] !== null));
        foreach (array_chunk($withCover, self::UPSERT_CHUNK) as $chunk) {
            $coverRows = array_map(
                fn (array $r) => array_intersect_key($r, array_flip(array_merge(['external_id'], self::COVER_COLUMNS, ['title', 'slug']))),
                $chunk
            );
            Place::query()->upsert($coverRows, ['external_id'], self::COVER_COLUMNS);
        }

        return $stats;
    }

    private function extractZip(string $dest): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($this->zipPath) !== true) {
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

    /**
     * Lit un fichier JSON-LD et retourne la ligne à insérer (ou 'skipped' / 'error').
     * Les colonnes JSON sont encodées ici car l'upsert ne passe pas par les casts Eloquent.
     *
     * @return array<string,mixed>|string
     */
    private function importFile(string $filePath): array|string
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

        $types = $this->extractTypes($data);
        $categorySlug = $this->resolveCategorySlug($title, $types);
        $status = $this->isHidden($types) ? 'hidden' : 'approved';
        [$isFree, $priceLevel] = $this->extractPricing($data, $types);
        $cover = $this->extractCover($data);
        $dates = $this->extractEventDates($data);

        return [
            'title' => Str::limit($title, 255),
            'slug' => Str::slug($title),
            'description' => $this->extractDescription($data),
            'category_id' => $this->categoryId($categorySlug),
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
            'address' => ($address = $this->extractAddress($data)) ? Str::limit($address, 255) : null,
            'status' => $status,
            'is_free' => $isFree,
            'price_level' => $priceLevel,
            'visit_duration_min' => self::VISIT_MINUTES[$categorySlug] ?? 60,
            'event_start_at' => $dates[0],
            'event_end_at' => $dates[1],
            'tags' => json_encode(array_values($this->extractTags($data)), JSON_UNESCAPED_UNICODE),
            'sources' => json_encode(['datatourisme']),
            'external_id' => $externalId,
            'cover_image_url' => $cover['url'],
            'cover_image_source' => $cover['url'] ? 'datatourisme' : null,
            'cover_image_license' => $cover['license'],
            'cover_image_author' => $cover['author'],
            'cover_image_attribution' => $cover['attribution'],
            'cover_image_page_url' => null,
            'cover_image_is_fallback' => false,
            'cover_image_fallback_reason' => null,
        ];
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

    /** @return array<int,string> types courts (sans préfixe schema:) */
    private function extractTypes(array $data): array
    {
        $types = $data['@type'] ?? [];
        if (is_string($types)) {
            $types = [$types];
        }
        $out = [];
        foreach ($types as $type) {
            $str = (string) $type;
            if (str_starts_with($str, 'schema:')) {
                continue;
            }
            $out[] = str_contains($str, '#') ? substr($str, strrpos($str, '#') + 1) : class_basename($str);
        }

        return $out;
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

    /**
     * Description : rdfs:comment (résumé), sinon hasDescription.shortDescription.fr.
     */
    private function extractDescription(array $data): ?string
    {
        $desc = $data['rdfs:comment'] ?? $data['schema:description'] ?? $data['description'] ?? null;
        $text = $this->frText($desc);

        if (! $text) {
            foreach ((array) ($data['hasDescription'] ?? []) as $d) {
                $text = $this->frText($d['shortDescription'] ?? null);
                if ($text) {
                    break;
                }
            }
        }

        $text = $text ? trim(strip_tags($text)) : null;

        return $text ? Str::limit($text, 2000) : null;
    }

    /** Extrait la valeur française d'un champ multilingue JSON-LD. */
    private function frText(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            if (isset($value['fr'])) {
                $fr = $value['fr'];

                return is_array($fr) ? ($fr[0] ?? null) : $fr;
            }
            if (isset($value['@value'])) {
                return $value['@value'];
            }
        }

        return null;
    }

    /**
     * Catégorie : heuristique "parc/jardin" sur le titre, puis premier type connu par ordre de spécificité.
     *
     * @param array<int,string> $types
     */
    private function resolveCategorySlug(string $title, array $types): string
    {
        $lower = mb_strtolower($title);
        foreach (['parc ', 'jardin', 'square ', 'bois ', 'forêt', 'foret '] as $word) {
            if (str_starts_with($lower, $word) || str_contains($lower, ' ' . $word)) {
                return 'parc-jardin';
            }
        }

        foreach (self::TYPE_TO_CATEGORY as $type => $slug) {
            if (in_array($type, $types, true)) {
                return $slug;
            }
        }

        return 'lieu-culturel';
    }

    /**
     * Hors sujet si l'objet porte un type masqué et aucun type culturel spécifique
     * (un château-hôtel reste un monument, un restaurant d'hôtel reste visible).
     *
     * @param array<int,string> $types
     */
    private function isHidden(array $types): bool
    {
        $hidden = array_intersect($types, self::HIDDEN_TYPES);
        if ($hidden === []) {
            return false;
        }

        $culturalTypes = array_diff(array_keys(self::TYPE_TO_CATEGORY), ['CulturalSite', 'EntertainmentAndEvent', 'FoodEstablishment']);

        return array_intersect($types, $culturalTypes) === [];
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
            $text = $this->frText($label);
            if ($text) {
                $tags[] = $text;
            }
        }

        return array_values(array_unique(array_filter(array_slice($tags, 0, 10))));
    }

    /**
     * Image principale fournie par le flux (hasMainRepresentation, sinon première hasRepresentation).
     *
     * @return array{url:?string,license:?string,author:?string,attribution:?string}
     */
    private function extractCover(array $data): array
    {
        $empty = ['url' => null, 'license' => null, 'author' => null, 'attribution' => null];

        $reps = [];
        foreach (['hasMainRepresentation', 'hasRepresentation'] as $key) {
            $val = $data[$key] ?? null;
            if (is_array($val)) {
                $reps = array_merge($reps, isset($val[0]) ? $val : [$val]);
            }
        }

        foreach ($reps as $rep) {
            foreach ((array) ($rep['ebucore:hasRelatedResource'] ?? []) as $res) {
                $locator = $res['ebucore:locator'] ?? null;
                $url = is_array($locator) ? ($locator[0] ?? null) : $locator;
                if (! is_string($url) || ! str_starts_with($url, 'http')) {
                    continue;
                }
                $annotation = ((array) ($rep['ebucore:hasAnnotation'] ?? []))[0] ?? [];
                $credits = (array) ($annotation['credits'] ?? []);

                return [
                    'url' => Str::limit($url, 2000, ''),
                    'license' => isset($annotation['ebucore:isCoveredBy']) ? Str::limit((string) $annotation['ebucore:isCoveredBy'], 255, '') : null,
                    'author' => isset($credits[0]) ? Str::limit((string) $credits[0], 255, '') : null,
                    'attribution' => $this->frText($annotation['ebucore:title'] ?? null),
                ];
            }
        }

        return $empty;
    }

    /**
     * Gratuité et niveau de prix à partir des offres (schema:priceSpecification.minPrice).
     * Sans offre : gratuit par défaut pour les parcs, lieux de culte, etc., sinon inconnu.
     *
     * @param array<int,string> $types
     * @return array{0:bool,1:?int}
     */
    private function extractPricing(array $data, array $types): array
    {
        $prices = [];
        $mentionsFree = false;

        foreach ((array) ($data['offers'] ?? []) as $offer) {
            foreach ((array) ($offer['schema:priceSpecification'] ?? []) as $spec) {
                $min = $spec['schema:minPrice'] ?? null;
                $min = is_array($min) ? ($min[0] ?? null) : $min;
                if ($min !== null && is_numeric($min)) {
                    $prices[] = (float) $min;
                }
                $label = mb_strtolower((string) ($this->frText($spec['name'] ?? null) ?? ''));
                if (str_contains($label, 'gratuit') || str_contains($label, 'entrée libre') || str_contains($label, 'accès libre')) {
                    $mentionsFree = true;
                }
            }
        }

        $paid = array_filter($prices, fn (float $p) => $p > 0);

        if ($paid === []) {
            if ($prices !== [] || $mentionsFree || array_intersect($types, self::FREE_BY_DEFAULT_TYPES) !== []) {
                return [true, null];
            }

            return [false, null];
        }

        $min = min($paid);
        $level = $min <= 6 ? 1 : ($min <= 15 ? 2 : 3);

        // "gratuit" mentionné parmi des tarifs payants : accès libre partiel (ex. parc gratuit, expo payante).
        return [$mentionsFree || in_array(0.0, $prices, true), $level];
    }

    /**
     * Période de l'événement (takesPlaceAt) : première date de début, dernière date de fin.
     *
     * @return array{0:?string,1:?string}
     */
    private function extractEventDates(array $data): array
    {
        $starts = [];
        $ends = [];
        foreach ((array) ($data['takesPlaceAt'] ?? []) as $period) {
            if (! is_array($period)) {
                continue;
            }
            if (! empty($period['startDate'])) {
                $starts[] = substr((string) $period['startDate'], 0, 10);
            }
            if (! empty($period['endDate'])) {
                $ends[] = substr((string) $period['endDate'], 0, 10);
            }
        }

        return [$starts ? min($starts) : null, $ends ? max($ends) : ( $starts ? max($starts) : null)];
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
