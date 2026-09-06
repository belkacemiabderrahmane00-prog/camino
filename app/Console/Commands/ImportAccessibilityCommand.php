<?php

namespace App\Console\Commands;

use App\Models\Place;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rapproche nos lieux de la base Acceslibre (données publiques d'accessibilité des ERP)
 * et remplit places.accessible / accessibility_note. Rapprochement par distance (< 80 m)
 * et similarité du nom, pour ne jamais attribuer l'accessibilité d'un commerce voisin.
 *
 * Deux temps possibles : `--csv=… --dump=… --export=database/data/accessibility.json` calcule
 * hors base (sur le PC), puis `--apply=database/data/accessibility.json` applique en production.
 */
class ImportAccessibilityCommand extends Command
{
    protected $signature = 'camino:import-accessibility
                            {--csv= : Export acceslibre.csv (data.gouv.fr)}
                            {--dump= : Dossier JSON-LD DATAtourisme pour rapprocher sans base de données}
                            {--export= : Écrit le résultat du rapprochement dans ce fichier JSON (external_id → accessibilité)}
                            {--apply= : Applique un fichier JSON exporté à la base}
                            {--dry-run : Analyse sans écrire}';

    protected $description = 'Importe l\'accessibilité PMR des lieux depuis Acceslibre';

    private const IDF = ['75', '77', '78', '91', '92', '93', '94', '95'];

    public function handle(): int
    {
        if ($apply = $this->option('apply')) {
            return $this->apply($apply);
        }

        $csv = $this->option('csv') ?: storage_path('app/acceslibre.csv');
        if (! is_file($csv)) {
            $this->error("Fichier introuvable : $csv");

            return self::FAILURE;
        }

        $grid = $this->index($csv);

        // Lieux à rapprocher : depuis le dump (sans base) ou depuis la base.
        $targets = [];
        if ($dump = $this->option('dump')) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dump, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (! $file->isFile() || ! str_ends_with(strtolower($file->getFilename()), '.json')) {
                    continue;
                }
                $d = json_decode((string) file_get_contents($file->getPathname()), true);
                if (! is_array($d)) {
                    continue;
                }
                $id = $d['@id'] ?? null;
                $geo = $d['isLocatedAt'][0]['schema:geo'] ?? [];
                $lat = (float) ($geo['schema:latitude'] ?? 0);
                $lng = (float) ($geo['schema:longitude'] ?? 0);
                $title = (string) ($d['rdfs:label']['fr'][0] ?? '');
                if (! is_string($id) || ! $lat || ! $lng || $title === '') {
                    continue;
                }
                $targets[] = ['key' => $id, 'title' => $title, 'lat' => $lat, 'lng' => $lng];
            }
        } else {
            Place::query()->whereNotNull('lat')->whereNotNull('lng')->select(['id', 'external_id', 'title', 'lat', 'lng'])
                ->chunkById(500, function ($places) use (&$targets) {
                    foreach ($places as $p) {
                        $targets[] = ['key' => $p->external_id ?: (string) $p->id, 'id' => $p->id, 'title' => $p->title, 'lat' => (float) $p->lat, 'lng' => (float) $p->lng];
                    }
                });
        }

        $matched = 0;
        $accessible = 0;
        $notAccessible = 0;
        $results = [];
        foreach ($targets as $t) {
            $best = $this->match($t, $grid);
            if (! $best) {
                continue;
            }
            $matched++;
            [$value, $note] = $this->evaluate($best);
            if ($value === true) {
                $accessible++;
            } elseif ($value === false) {
                $notAccessible++;
            }
            $results[$t['key']] = ['a' => $value, 'n' => $note, 'id' => $t['id'] ?? null];
        }
        $this->info(count($targets) . " lieux examinés, $matched rapprochés : $accessible accessibles, $notAccessible non accessibles, " . ($matched - $accessible - $notAccessible) . ' sans conclusion.');

        if ($export = $this->option('export')) {
            $out = [];
            foreach ($results as $key => $r) {
                $out[$key] = [$r['a'], $r['n']];
            }
            file_put_contents($export, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info('Exporté : ' . $export . ' (' . round(filesize($export) / 1024) . ' Ko)');

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($results) {
            foreach ($results as $key => $r) {
                $q = $r['id'] ? DB::table('places')->where('id', $r['id']) : DB::table('places')->where('external_id', $key);
                $q->update(['accessible' => $r['a'], 'accessibility_source' => 'acceslibre', 'accessibility_note' => $r['n']]);
            }
        });
        $this->info(count($results) . ' lieux mis à jour.');

        return self::SUCCESS;
    }

    /** Applique un fichier exporté : mises à jour groupées par external_id. */
    private function apply(string $path): int
    {
        if (! is_file($path)) {
            $this->error("Fichier introuvable : $path");

            return self::FAILURE;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            $this->error('JSON invalide.');

            return self::FAILURE;
        }
        $updated = 0;
        $driver = DB::connection()->getDriverName();
        foreach (array_chunk($data, 300, true) as $chunk) {
            if ($driver === 'pgsql') {
                $values = [];
                $bindings = [];
                foreach ($chunk as $key => [$a, $n]) {
                    $values[] = '(?, ?::boolean, ?)';
                    $bindings[] = $key;
                    $bindings[] = $a === null ? null : ($a ? 'true' : 'false');
                    $bindings[] = $n;
                }
                $updated += DB::affectingStatement('UPDATE places SET accessible = v.a, accessibility_source = \'acceslibre\', accessibility_note = v.n FROM (VALUES ' . implode(',', $values) . ') AS v(external_id, a, n) WHERE places.external_id = v.external_id', $bindings);
            } else {
                foreach ($chunk as $key => [$a, $n]) {
                    $updated += DB::table('places')->where('external_id', $key)->update(['accessible' => $a, 'accessibility_source' => 'acceslibre', 'accessibility_note' => $n]);
                }
            }
        }
        $this->info("Accessibilité appliquée : $updated lieux.");

        return self::SUCCESS;
    }

    /** Index spatial des ERP d'Île-de-France (cellules de ~100 m). */
    private function index(string $csv): array
    {
        $fh = fopen($csv, 'rb');
        $header = fgetcsv($fh);
        $col = array_flip($header);
        foreach (['name', 'postal_code', 'longitude', 'latitude', 'entree_pmr', 'entree_plain_pied', 'entree_ascenseur', 'entree_marches', 'entree_marches_rampe', 'sanitaires_adaptes', 'accueil_cheminement_plain_pied'] as $c) {
            if (! isset($col[$c])) {
                throw new \RuntimeException("Colonne manquante : $c");
            }
        }
        $grid = [];
        $rows = 0;
        while (($r = fgetcsv($fh)) !== false) {
            $pc = substr((string) ($r[$col['postal_code']] ?? ''), 0, 2);
            if (! in_array($pc, self::IDF, true)) {
                continue;
            }
            $lat = (float) $r[$col['latitude']];
            $lng = (float) $r[$col['longitude']];
            if (! $lat || ! $lng) {
                continue;
            }
            $grid[$this->cell($lat, $lng)][] = [
                'name' => (string) $r[$col['name']],
                'lat' => $lat,
                'lng' => $lng,
                'pmr' => $this->bool($r[$col['entree_pmr']]),
                'plain_pied' => $this->bool($r[$col['entree_plain_pied']]),
                'ascenseur' => $this->bool($r[$col['entree_ascenseur']]),
                'marches' => is_numeric($r[$col['entree_marches']]) ? (int) $r[$col['entree_marches']] : null,
                'rampe' => (string) $r[$col['entree_marches_rampe']],
                'wc' => $this->bool($r[$col['sanitaires_adaptes']]),
                'accueil_plain_pied' => $this->bool($r[$col['accueil_cheminement_plain_pied']]),
            ];
            $rows++;
        }
        fclose($fh);
        $this->info("$rows établissements franciliens indexés.");

        return $grid;
    }

    private function cell(float $lat, float $lng): string
    {
        return floor($lat * 1000) . ':' . floor($lng * 1000);
    }

    /** @return array<string,mixed>|null */
    private function match(array $place, array $grid): ?array
    {
        $title = $this->normalize($place['title']);
        $tokens = $this->tokens($title);
        $best = null;
        $bestScore = 0.0;
        $clat = (int) floor($place['lat'] * 1000);
        $clng = (int) floor($place['lng'] * 1000);
        for ($i = -1; $i <= 1; $i++) {
            for ($j = -1; $j <= 1; $j++) {
                foreach ($grid[($clat + $i) . ':' . ($clng + $j)] ?? [] as $e) {
                    $d = $this->meters($place['lat'], $place['lng'], $e['lat'], $e['lng']);
                    if ($d > 80) {
                        continue;
                    }
                    $sim = $this->similarity($title, $tokens, $this->normalize($e['name']));
                    if ($sim < 0.5) {
                        continue;
                    }
                    $score = $sim - $d / 400;
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = $e;
                    }
                }
            }
        }

        return $best;
    }

    /** @return array{0:?bool,1:?string} */
    private function evaluate(array $e): array
    {
        $notes = [];
        $value = null;
        $rampe = in_array($e['rampe'], ['fixe', 'amovible'], true);
        if ($e['pmr'] === true || $e['plain_pied'] === true || $e['ascenseur'] === true || $rampe) {
            $value = true;
            $notes[] = $e['plain_pied'] === true ? 'Entrée de plain-pied' : ($e['ascenseur'] === true ? 'Entrée par ascenseur' : ($rampe ? 'Rampe d\'accès' : 'Entrée accessible PMR'));
        } elseif ($e['plain_pied'] === false && ($e['marches'] ?? 0) >= 1 && $e['ascenseur'] !== true && $e['pmr'] !== true) {
            $value = false;
            $notes[] = $e['marches'] . ' marche' . ($e['marches'] > 1 ? 's' : '') . ' à l\'entrée, sans rampe';
        }
        if ($e['wc'] === true) {
            $notes[] = 'WC adaptés';
        }
        if ($e['accueil_plain_pied'] === true) {
            $notes[] = 'Circulation intérieure de plain-pied';
        }

        return [$value, $notes === [] ? null : Str::limit(implode(' · ', $notes), 250, '')];
    }

    private function bool(mixed $v): ?bool
    {
        return match (strtolower(trim((string) $v))) {
            'true', '1', 'oui' => true,
            'false', '0', 'non' => false,
            default => null,
        };
    }

    private function normalize(string $s): string
    {
        $s = Str::ascii(mb_strtolower($s));
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? $s;
        $s = preg_replace('/\b(le|la|les|l|de|du|des|d|et|a|au|aux|the|of|en|sur)\b/', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    /** @return array<int,string> */
    private function tokens(string $s): array
    {
        return array_values(array_filter(explode(' ', $s), fn ($t) => strlen($t) >= 3));
    }

    private function similarity(string $a, array $ta, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b || str_contains($a, $b) || str_contains($b, $a)) {
            return 1.0;
        }
        $tb = $this->tokens($b);
        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        return count(array_intersect($ta, $tb)) / max(1, min(count($ta), count($tb)));
    }

    private function meters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $x = deg2rad($lng2 - $lng1) * cos(deg2rad(($lat1 + $lat2) / 2));
        $y = deg2rad($lat2 - $lat1);

        return sqrt($x * $x + $y * $y) * 6371000;
    }
}
