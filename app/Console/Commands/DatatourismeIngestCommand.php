<?php

namespace App\Console\Commands;

use App\Services\DatatourismeImporter;
use Illuminate\Console\Command;

class DatatourismeIngestCommand extends Command
{
    protected $signature = 'camino:ingest-datatourisme
                            {--file= : Chemin vers l\'archive ZIP du flux JSON}
                            {--full : Full sync (ignoré)}
                            {--dry-run : Log seulement, ne pas écrire en base}';

    protected $description = 'Ingestion DATAtourisme → POI CAMINO (Île-de-France). Ne jamais appeler l\'API depuis le front.';

    public function handle(): int
    {
        $zipPath = $this->option('file') ?? config('datatourisme.zip_path');

        if (empty($zipPath) || ! is_file($zipPath)) {
            $this->error('Archive ZIP manquante.');
            $this->line('Usage : php artisan camino:ingest-datatourisme --file=/chemin/vers/flux.zip');
            $this->line('Ou définir DATATOURISME_ZIP_PATH dans .env');
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Mode dry-run : aucune écriture en base.');
        }

        $this->info("Import depuis : {$zipPath}");

        $importer = new DatatourismeImporter($zipPath, $dryRun);
        $stats = $importer->run();

        if (isset($stats['message'])) {
            $this->error($stats['message']);
            return self::FAILURE;
        }

        $this->table(
            ['Créés', 'Mis à jour', 'Ignorés', 'Erreurs'],
            [[$stats['created'], $stats['updated'], $stats['skipped'], $stats['errors']]]
        );

        $this->info('Import terminé.');

        return self::SUCCESS;
    }
}
