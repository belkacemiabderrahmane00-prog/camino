<?php

namespace App\Console\Commands;

use App\Models\Place;
use App\Services\TranslationService;
use Illuminate\Console\Command;

/**
 * Pré-traduit les descriptions des lieux (les plus visibles d'abord) pour une langue.
 * Les traductions manquantes sont sinon faites à la volée quand un visiteur ouvre la fiche.
 */
class TranslatePlacesCommand extends Command
{
    protected $signature = 'camino:translate-places {--locale=en : en ou zh} {--limit=100 : nombre de lieux} {--sleep=0 : pause en secondes entre deux lieux}';

    protected $description = 'Traduit en avance les descriptions des lieux (DeepL si clé, sinon MyMemory)';

    public function handle(TranslationService $translator): int
    {
        $locale = (string) $this->option('locale');
        if (! in_array($locale, ['en', 'zh'], true)) {
            $this->error('Langue non gérée : ' . $locale);

            return self::FAILURE;
        }
        if (! $translator->enabled()) {
            $this->warn('Traduction désactivée (CAMINO_TRANSLATE_PLACES=false).');

            return self::SUCCESS;
        }
        $places = Place::visible()
            ->whereNotNull('description')->where('description', '!=', '')
            ->whereDoesntHave('translations', fn ($q) => $q->where('locale', $locale)->where('field', 'description'))
            ->orderByRaw('(cover_image_url is null) asc')
            ->orderByDesc('id')
            ->limit((int) $this->option('limit'))
            ->get();
        $this->info(sprintf('%d lieux à traduire en %s (%s)', $places->count(), $locale, $translator->provider()));
        $done = 0;
        foreach ($places as $place) {
            $text = $place->translatedDescription($locale, $translator, true);
            if ($text === null) {
                $this->warn('  échec : ' . $place->title . ' (quota atteint ou service indisponible ?)');

                break;
            }
            $done++;
            $this->line('  ✓ ' . $place->title);
            if ((int) $this->option('sleep') > 0) {
                sleep((int) $this->option('sleep'));
            }
        }
        $this->info("$done traduction(s) enregistrée(s).");

        return self::SUCCESS;
    }
}
