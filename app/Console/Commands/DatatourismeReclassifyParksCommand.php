<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Place;
use Illuminate\Console\Command;

class DatatourismeReclassifyParksCommand extends Command
{
    protected $signature = 'camino:reclassify-datatourisme-parcs';

    protected $description = 'Reclasse en "Parc / Jardin" les lieux DATAtourisme dont le titre contient parc/jardin/square/bois/forêt.';

    public function handle(): int
    {
        $this->info('Reclassement des parcs / jardins pour les lieux DATAtourisme...');

        $parcCategory = Category::where('slug', 'parc-jardin')->first();
        if (! $parcCategory) {
            $this->error('Catégorie "parc-jardin" introuvable. Vérifiez vos seeders.');

            return self::FAILURE;
        }

        $keywords = ['parc', 'jardin', 'square', 'bois', 'forêt', 'foret'];

        $baseQuery = Place::query()
            ->where('category_id', '!=', $parcCategory->id)
            ->whereJsonContains('sources', 'datatourisme')
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $word) {
                    $q->orWhereRaw('LOWER(title) LIKE ?', ['%' . mb_strtolower($word, 'UTF-8') . '%']);
                }
            });

        $total = $baseQuery->count();
        if ($total === 0) {
            $this->info('Aucun lieu à reclasser.');

            return self::SUCCESS;
        }

        $this->info("Lieux candidats à reclasser : {$total}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;

        $baseQuery->chunkById(200, function ($places) use (&$updated, $parcCategory, $bar) {
            foreach ($places as $place) {
                $place->category_id = $parcCategory->id;
                $place->save();
                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Lieux mis à jour : {$updated}");

        return self::SUCCESS;
    }
}

