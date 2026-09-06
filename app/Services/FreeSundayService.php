<?php

namespace App\Services;

use App\Models\Place;
use Illuminate\Support\Carbon;

/**
 * « Gratuit ce dimanche » : le premier dimanche du mois, les musées et monuments nationaux sont gratuits
 * (certains seulement de novembre à mars). La règle vient des descriptions DATAtourisme (« premier dimanche du mois »)
 * complétée par une liste des grands établissements dont la gratuité est connue.
 */
class FreeSundayService
{
    /** Établissements connus : motif sur le titre → gratuit toute l'année (false) ou seulement de novembre à mars (true). */
    private const KNOWN = [
        'orsay' => false, 'orangerie' => false, 'pompidou' => false, 'musée picasso' => false, 'quai branly' => false,
        'cluny' => false, 'guimet' => false, 'delacroix' => false, 'cité de l\'architecture' => false, 'musée de l\'immigration' => false,
        'musée rodin' => true, 'arc de triomphe' => true, 'panthéon' => true, 'sainte-chapelle' => true, 'conciergerie' => true,
        'château de versailles' => true, 'basilique de saint-denis' => true, 'château de vincennes' => true, 'villa savoye' => true, 'château de champs' => true,
        'malmaison' => false, 'musée de la renaissance' => false, 'château d\'écouen' => false, 'sèvres' => false, 'port-royal' => false,
    ];

    private const NOTE_PATTERN = '/(premier|1er)\s+dimanche/iu';

    public function isFirstSunday(Carbon $date): bool
    {
        return $date->isSunday() && $date->day <= 7;
    }

    /** Prochain premier dimanche du mois (aujourd'hui compris). */
    public function nextFirstSunday(?Carbon $from = null): Carbon
    {
        $d = ($from ?? Carbon::now(config('app.timezone')))->copy()->startOfDay();
        if ($this->isFirstSunday($d)) {
            return $d;
        }
        $first = $d->copy()->startOfMonth();
        $candidate = $first->copy()->next(Carbon::SUNDAY);
        if ($first->isSunday()) {
            $candidate = $first;
        }
        if ($candidate->lt($d)) {
            $first = $d->copy()->addMonthNoOverflow()->startOfMonth();
            $candidate = $first->isSunday() ? $first : $first->copy()->next(Carbon::SUNDAY);
        }

        return $candidate;
    }

    /** Le lieu est-il gratuit le premier dimanche du mois (à la date donnée, pour les gratuités hivernales) ? */
    public function appliesTo(Place $place, ?Carbon $date = null): bool
    {
        $date ??= $this->nextFirstSunday();
        $winter = $date->month >= 11 || $date->month <= 3;
        $title = mb_strtolower((string) $place->title);
        foreach (self::KNOWN as $needle => $winterOnly) {
            if (str_contains($title, $needle)) {
                return ! $winterOnly || $winter;
            }
        }
        $slug = $place->category?->slug;
        if (! in_array($slug, ['musee', 'monument', 'lieu-culturel', null], true)) {
            return false;
        }

        return (bool) preg_match(self::NOTE_PATTERN, (string) $place->description);
    }

    /**
     * Contrainte SQL équivalente (approchée : titre connu ou mention dans la description), portable SQLite / Postgres.
     */
    public function scope($query): void
    {
        $query->where(function ($q) {
            $q->whereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ['%premier dimanche%'])
                ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ['%1er dimanche%']);
            foreach (array_keys(self::KNOWN) as $needle) {
                $q->orWhereRaw('LOWER(title) LIKE ?', ['%' . $needle . '%']);
            }
        });
    }

    /** Libellé court pour l'interface : « dim. 5 oct. » */
    public function label(?Carbon $from = null): string
    {
        return $this->nextFirstSunday($from)->translatedFormat('D j M');
    }
}
