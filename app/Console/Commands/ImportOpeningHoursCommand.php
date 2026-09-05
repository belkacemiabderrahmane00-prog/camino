<?php

namespace App\Console\Commands;

use App\Services\OpeningHoursParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Met à jour uniquement la colonne opening_hours des lieux à partir du dump DATAtourisme
 * (dossier extrait ou archive ZIP), sans toucher aux photos ni aux autres colonnes.
 */
class ImportOpeningHoursCommand extends Command
{
    protected $signature = 'camino:import-opening-hours
                            {--dir= : Dossier contenant les JSON-LD (défaut : storage/app/datatourisme/idf-poi-json/objects)}
                            {--zip= : Archive ZIP à extraire si le dossier n\'existe pas}
                            {--dry-run : Analyse sans écrire en base}';

    protected $description = 'Importe les horaires d\'ouverture DATAtourisme dans places.opening_hours';

    public function handle(OpeningHoursParser $parser): int
    {
        $dir = $this->option('dir') ?: storage_path('app/datatourisme/idf-poi-json/objects');
        $tmp = null;
        if (! is_dir($dir)) {
            $zip = $this->option('zip') ?: storage_path('app/datatourisme/idf-poi.json.zip');
            if (! is_file($zip) || ! class_exists(\ZipArchive::class)) {
                $this->error("Dossier introuvable ($dir) et aucune archive exploitable.");

                return self::FAILURE;
            }
            $tmp = sys_get_temp_dir() . '/camino-hours-' . uniqid();
            $archive = new \ZipArchive();
            $archive->open($zip);
            $archive->extractTo($tmp);
            $archive->close();
            $dir = is_dir($tmp . '/objects') ? $tmp . '/objects' : $tmp;
        }

        $rows = [];
        $stats = ['files' => 0, 'with_hours' => 0, 'structured' => 0, 'parsed' => 0, 'period' => 0];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with(strtolower($file->getFilename()), '.json')) {
                continue;
            }
            $stats['files']++;
            $data = json_decode((string) file_get_contents($file->getPathname()), true);
            if (! is_array($data)) {
                continue;
            }
            $id = $data['@id'] ?? $data['dc:identifier'] ?? $data['identifier'] ?? null;
            if (is_array($id)) {
                $id = $id['@value'] ?? ($id[0] ?? null);
            }
            if (! $id) {
                continue;
            }
            $specs = $data['isLocatedAt'][0]['schema:openingHoursSpecification'] ?? [];
            $hours = $specs ? $parser->normalize($specs) : null;
            if ($hours === null) {
                continue;
            }
            $stats['with_hours']++;
            $stats[$hours['confidence']] = ($stats[$hours['confidence']] ?? 0) + 1;
            $rows[(string) $id] = json_encode($hours, JSON_UNESCAPED_UNICODE);
        }

        $this->info(sprintf('%d fichiers, %d avec horaires (structurés %d, déduits du texte %d, période seule %d).', $stats['files'], $stats['with_hours'], $stats['structured'] ?? 0, $stats['parsed'] ?? 0, $stats['period'] ?? 0));

        if ($tmp) {
            $this->removeDir($tmp);
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        // Identifiants tels qu'enregistrés par l'importateur principal : dc:identifier ou @id.
        $updated = 0;
        foreach (array_chunk($rows, 300, true) as $chunk) {
            $values = [];
            $bindings = [];
            foreach ($chunk as $externalId => $json) {
                $values[] = '(?, ?::jsonb)';
                $bindings[] = $externalId;
                $bindings[] = $json;
            }
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                $sql = 'UPDATE places SET opening_hours = v.hours::json, updated_at = NOW() FROM (VALUES ' . implode(',', $values) . ') AS v(external_id, hours) WHERE places.external_id = v.external_id';
                $updated += DB::affectingStatement($sql, $bindings);
            } else {
                foreach ($chunk as $externalId => $json) {
                    $updated += DB::table('places')->where('external_id', $externalId)->update(['opening_hours' => $json]);
                }
            }
            $this->output->write('.');
        }
        $this->newLine();
        $this->info("$updated lieux mis à jour.");

        return self::SUCCESS;
    }

    private function removeDir(string $dir): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
