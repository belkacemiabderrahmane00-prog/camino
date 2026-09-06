<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reclasse les lieux DATAtourisme dans « Librairies & bibliothèques » et « Ateliers & artisans »
 * à partir des types du flux et des titres. `--dump=… --export=fichier.json` calcule sur le PC,
 * `--apply=fichier.json` applique en production (au déploiement).
 */
class ReclassifyCategoriesCommand extends Command
{
    protected $signature = 'camino:reclassify-categories
                            {--dir= : Dossier JSON-LD DATAtourisme (défaut : storage/app/datatourisme/idf-poi-json/objects)}
                            {--export= : Écrit external_id → slug de catégorie dans ce fichier JSON}
                            {--apply= : Applique un fichier JSON exporté à la base}
                            {--dry-run : Analyse sans écrire}';

    protected $description = 'Reclasse bibliothèques, librairies et ateliers d\'artisans';

    private const FOOD_OR_EVENT = ['FoodEstablishment', 'schema:FoodEstablishment', 'Restaurant', 'schema:Restaurant', 'BistroOrWineBar', 'Winery', 'schema:Winery', 'Event', 'schema:Event', 'CulturalEvent', 'ExhibitionEvent', 'schema:ExhibitionEvent', 'Theater', 'ReligiousSite', 'InterpretationCentre', 'Museum', 'IndustrialSite', 'TechnicalHeritage', 'RemarkableBuilding', 'Cinema', 'Accommodation', 'Hotel'];

    public function handle(): int
    {
        if ($apply = $this->option('apply')) {
            return $this->apply($apply);
        }

        $dir = $this->option('dir') ?: storage_path('app/datatourisme/idf-poi-json/objects');
        if (! is_dir($dir)) {
            $this->error("Dossier introuvable : $dir");

            return self::FAILURE;
        }

        $targets = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with(strtolower($file->getFilename()), '.json')) {
                continue;
            }
            $d = json_decode((string) file_get_contents($file->getPathname()), true);
            if (! is_array($d)) {
                continue;
            }
            $id = $d['@id'] ?? null;
            if (! is_string($id)) {
                continue;
            }
            $types = (array) ($d['@type'] ?? []);
            $title = (string) ($d['rdfs:label']['fr'][0] ?? '');

            if (in_array('Library', $types, true) || preg_match('/biblioth[eè]que|m[eé]diath[eè]que|librairie|bouquinerie/iu', $title)) {
                $targets[$id] = 'librairies-bibliotheques';

                continue;
            }
            $excluded = (bool) preg_match('/th[ée][âa]tre|paroisse|salle |visite|baludik|cin[ée]ma|od[ée]on|lumi[èe]res|caf[ée]|wok|cuisine|p[âa]tisserie|boulanger|path[ée]|halle|h[ôo]tel|tripper|gourmand/iu', $title);
            $craftType = array_intersect($types, ['CraftsmanShop', 'LocalProductsShop']) !== [] && ! $excluded;
            $craftTitle = preg_match('/\b(ateliers?|artisans?|artistes)\b/iu', $title)
                && array_intersect($types, self::FOOD_OR_EVENT) === []
                && ! $excluded;
            if ($craftType || $craftTitle) {
                $targets[$id] = 'ateliers-artisans';
            }
        }

        $libs = count(array_filter($targets, fn ($s) => $s === 'librairies-bibliotheques'));
        $this->info(count($targets) . " lieux à reclasser ($libs bibliothèques/librairies, " . (count($targets) - $libs) . ' ateliers).');

        if ($export = $this->option('export')) {
            file_put_contents($export, json_encode($targets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $this->info('Exporté : ' . $export);

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        return $this->applyMap($targets);
    }

    private function apply(string $path): int
    {
        if (! is_file($path)) {
            $this->error("Fichier introuvable : $path");

            return self::FAILURE;
        }
        $map = json_decode((string) file_get_contents($path), true);
        if (! is_array($map)) {
            $this->error('JSON invalide.');

            return self::FAILURE;
        }

        return $this->applyMap($map);
    }

    /** @param array<string,string> $map external_id → slug */
    private function applyMap(array $map): int
    {
        $ids = Category::whereIn('slug', ['librairies-bibliotheques', 'ateliers-artisans'])->pluck('id', 'slug');
        if ($ids->count() < 2) {
            $this->error('Catégories manquantes : lance d\'abord le CategorySeeder.');

            return self::FAILURE;
        }
        $updated = 0;
        foreach ($ids as $slug => $categoryId) {
            $keys = array_keys(array_filter($map, fn ($s) => $s === $slug));
            foreach (array_chunk($keys, 300) as $chunk) {
                $updated += DB::table('places')->whereIn('external_id', $chunk)->where(fn ($q) => $q->where('category_id', '!=', $categoryId)->orWhere('status', 'hidden'))
                    ->update(['category_id' => $categoryId, 'status' => 'approved']);
            }
        }
        $this->info("Catégories appliquées : $updated lieux modifiés.");

        return self::SUCCESS;
    }
}
