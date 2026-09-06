<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Normalise les horaires DATAtourisme (schema:openingHoursSpecification) en une structure simple :
 *
 *  [
 *    'periods' => [['from' => 'Y-m-d'|null, 'through' => 'Y-m-d'|null, 'opens' => 'HH:MM'|null, 'closes' => 'HH:MM'|null, 'days' => [1..7]|null, 'note' => string|null]],
 *    'closed_days' => [1..7],   // jours ISO fermés toute l'année (déduits du texte)
 *    'confidence' => 'structured' | 'parsed' | 'period' | 'unknown',
 *  ]
 *
 * Le flux ne fournit presque jamais de jour de la semaine structuré : on lit la note en français
 * (« fermé le lundi », « du mardi au dimanche », « 10h-18h ») avec des règles prudentes.
 */
class OpeningHoursParser
{
    private const DAYS = ['lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7];

    /**
     * @param array<int,array<string,mixed>> $specs Entrées schema:openingHoursSpecification
     */
    public function normalize(array $specs): ?array
    {
        $periods = [];
        $closedDays = [];
        $confidence = 'unknown';

        foreach ($specs as $spec) {
            if (! is_array($spec)) {
                continue;
            }
            $opens = $this->time($spec['schema:opens'] ?? null);
            $closes = $this->time($spec['schema:closes'] ?? null);
            $from = $this->date($spec['schema:validFrom'] ?? null);
            $through = $this->date($spec['schema:validThrough'] ?? null);
            $note = $this->frNote($spec);
            $days = $this->structuredDays($spec['schema:dayOfWeek'] ?? null);

            $parsed = $note ? $this->parseNote($note) : ['closed_days' => [], 'open_days' => null, 'opens' => null, 'closes' => null];
            if ($opens === null && $closes === null && $parsed['opens'] !== null) {
                $opens = $parsed['opens'];
                $closes = $parsed['closes'];
                $confidence = $confidence === 'structured' ? 'structured' : 'parsed';
            } elseif ($opens !== null) {
                $confidence = 'structured';
            }
            if ($days === null && $parsed['open_days'] !== null) {
                $days = $parsed['open_days'];
            }
            foreach ($parsed['closed_days'] as $d) {
                $closedDays[$d] = true;
            }

            if ($opens === null && $closes === null && $days === null && $from === null && $through === null && $note === null) {
                continue;
            }

            $periods[] = [
                'from' => $from,
                'through' => $through,
                'opens' => $opens,
                'closes' => $closes,
                'days' => $days,
                'note' => $note ? mb_substr($note, 0, 240) : null,
            ];
        }

        if ($periods === []) {
            return null;
        }
        if ($confidence === 'unknown') {
            $confidence = $closedDays !== [] ? 'parsed' : 'period';
        }

        return [
            'periods' => $periods,
            'closed_days' => array_map('intval', array_keys($closedDays)),
            'confidence' => $confidence,
        ];
    }

    /**
     * Fenêtre d'ouverture pour une date donnée.
     *
     * @return array{status:'open'|'closed'|'unknown', opens:?int, closes:?int, note:?string} minutes depuis minuit
     */
    public function windowFor(?array $hours, Carbon $date): array
    {
        if (! $hours || empty($hours['periods'])) {
            return ['status' => 'unknown', 'opens' => null, 'closes' => null, 'note' => null];
        }
        $dow = (int) $date->isoWeekday();
        if (in_array($dow, $hours['closed_days'] ?? [], true)) {
            return ['status' => 'closed', 'opens' => null, 'closes' => null, 'note' => $this->t('Fermé le :day', ['day' => $this->t($this->dayName($dow))])];
        }

        $ymd = $date->format('Y-m-d');
        $md = $date->format('m-d');
        $matching = [];
        $hasDated = false;
        foreach ($hours['periods'] as $p) {
            if ($p['from'] || $p['through']) {
                $hasDated = true;
                if (! $this->inPeriod($ymd, $md, $p['from'], $p['through'])) {
                    continue;
                }
            }
            if ($p['days'] !== null && ! in_array($dow, $p['days'], true)) {
                continue;
            }
            $matching[] = $p;
        }

        if ($matching === []) {
            // Des périodes datées existent mais aucune ne couvre la date : on considère fermé
            // seulement si elles portent des horaires (sinon ce sont de simples périodes de validité).
            $withHours = array_filter($hours['periods'], fn ($p) => $p['opens'] !== null);
            if ($hasDated && $withHours !== []) {
                return ['status' => 'closed', 'opens' => null, 'closes' => null, 'note' => $this->t('Hors période d\'ouverture')];
            }
            $anyDays = array_filter($hours['periods'], fn ($p) => $p['days'] !== null);
            if ($anyDays !== [] && $withHours === []) {
                return ['status' => 'closed', 'opens' => null, 'closes' => null, 'note' => $this->t('Fermé le :day', ['day' => $this->t($this->dayName($dow))])];
            }

            return ['status' => 'unknown', 'opens' => null, 'closes' => null, 'note' => $hours['periods'][0]['note'] ?? null];
        }

        // Fenêtre la plus large parmi les périodes applicables.
        $opens = null;
        $closes = null;
        $note = null;
        foreach ($matching as $p) {
            $note = $note ?? $p['note'];
            if ($p['opens'] === null) {
                continue;
            }
            $o = $this->minutes($p['opens']);
            $c = $p['closes'] ? $this->minutes($p['closes']) : 23 * 60;
            $opens = $opens === null ? $o : min($opens, $o);
            $closes = $closes === null ? $c : max($closes, $c);
        }
        if ($opens === null) {
            return ['status' => 'unknown', 'opens' => null, 'closes' => null, 'note' => $note];
        }

        return ['status' => 'open', 'opens' => $opens, 'closes' => $closes, 'note' => $note];
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{closed_days:array<int,int>, open_days:?array<int,int>, opens:?string, closes:?string} */
    public function parseNote(string $note): array
    {
        $t = mb_strtolower($note);
        $t = str_replace(['’', '\''], ' ', $t);
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        $closed = [];
        $openDays = null;

        // « fermé le lundi », « fermé les lundis et mardis », « fermeture le mardi », « sauf le lundi », « sauf lundi »
        if (preg_match_all('/(?:ferm[ée]e?s?|fermeture|sauf)(?: hebdomadaire)?(?: le| les| tous les)? ((?:(?:lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)s?(?:,| et | & | \/ |-)?(?: ?les? )?\s?)+)/u', $t, $m)) {
            foreach ($m[1] as $list) {
                if (str_contains($list, '-') && preg_match('/(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)s?\s?-\s?(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)/u', $list, $r)) {
                    foreach ($this->range(self::DAYS[$r[1]], self::DAYS[$r[2]]) as $d) {
                        $closed[$d] = true;
                    }

                    continue;
                }
                foreach (self::DAYS as $name => $num) {
                    if (preg_match('/\b' . $name . 's?\b/u', $list)) {
                        $closed[$num] = true;
                    }
                }
            }
        }

        // « du mardi au dimanche », « mercredi-lundi », « lundi-samedi », « ouvert du lundi au vendredi »
        if (preg_match('/(?:du |ouvert du |ouverts du )?(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)s?\s?(?:au|-|à)\s?(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)s?/u', $t, $r)) {
            $openDays = $this->range(self::DAYS[$r[1]], self::DAYS[$r[2]]);
        }
        // « ouvert le mercredi et le samedi » / « exposition ouverte le mercredi et le samedi »
        if ($openDays === null && preg_match('/ouverte?s? (?:le |les |tous les )?((?:(?:lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)s?(?:,| et | & )?(?: ?les? )?\s?)+)/u', $t, $r)) {
            $days = [];
            foreach (self::DAYS as $name => $num) {
                if (preg_match('/\b' . $name . 's?\b/u', $r[1])) {
                    $days[] = $num;
                }
            }
            if ($days !== [] && count($days) < 7) {
                $openDays = $days;
            }
        }
        if (preg_match('/tous les jours/u', $t) && $openDays === null && $closed === []) {
            $openDays = [1, 2, 3, 4, 5, 6, 7];
        }
        if ($openDays !== null) {
            $openDays = array_values(array_filter($openDays, fn ($d) => ! isset($closed[$d])));
        }

        // Première plage « 10h-18h », « 10h30 à 18h », « de 9h30 à 22h30 », « 10h00-19h00 »
        $opens = null;
        $closes = null;
        if (preg_match('/(\d{1,2})\s?h\s?(\d{2})?\s?(?:-|–|à|a|jusqu à)\s?(\d{1,2})\s?h\s?(\d{2})?/u', $t, $r)) {
            $o = (int) $r[1];
            $c = (int) $r[3];
            if ($o >= 0 && $o <= 23 && $c >= 1 && $c <= 24 && $c > $o) {
                $opens = sprintf('%02d:%02d', $o, (int) ($r[2] ?? 0));
                $closes = sprintf('%02d:%02d', min(23, $c), $c === 24 ? 59 : (int) ($r[4] ?? 0));
            }
        }

        return ['closed_days' => array_map('intval', array_keys($closed)), 'open_days' => $openDays, 'opens' => $opens, 'closes' => $closes];
    }

    /** @return array<int,int> */
    private function range(int $from, int $to): array
    {
        $days = [];
        $d = $from;
        for ($i = 0; $i < 7; $i++) {
            $days[] = $d;
            if ($d === $to) {
                break;
            }
            $d = $d % 7 + 1;
        }

        return $days;
    }

    private function inPeriod(string $ymd, string $md, ?string $from, ?string $through): bool
    {
        if ($from && $through) {
            // Période à cheval sur deux années (octobre → mars) : on compare sur mois-jour.
            if ($from > $through) {
                return $md >= substr($from, 5) || $md <= substr($through, 5);
            }
            // Période annuelle complète : valable chaque année (le flux n'est pas toujours remis à jour).
            if (substr($from, 5) === '01-01' && substr($through, 5) === '12-31') {
                return true;
            }

            return $ymd >= $from && $ymd <= $through;
        }
        if ($from) {
            return $ymd >= $from;
        }
        if ($through) {
            return $ymd <= $through;
        }

        return true;
    }

    private function structuredDays(mixed $value): ?array
    {
        if (! $value) {
            return null;
        }
        $map = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 7];
        $days = [];
        foreach ((array) $value as $v) {
            $key = strtolower(basename(str_replace(['schema:', 'http://schema.org/'], '', (string) $v)));
            if (isset($map[$key])) {
                $days[] = $map[$key];
            }
        }

        return $days === [] ? null : array_values(array_unique($days));
    }

    private function frNote(array $spec): ?string
    {
        $info = $spec['additionalInformation'] ?? null;
        if (is_array($info)) {
            $fr = $info['fr'] ?? null;
            $fr = is_array($fr) ? ($fr[0] ?? null) : $fr;

            return is_string($fr) && trim($fr) !== '' ? trim($fr) : null;
        }

        return is_string($info) && trim($info) !== '' ? trim($info) : null;
    }

    private function time(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{1,2}):(\d{2})/', $value, $m)) {
            return null;
        }

        return sprintf('%02d:%02d', min(23, (int) $m[1]), (int) $m[2]);
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
            return null;
        }

        return $m[1];
    }

    private function minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    /** Traduction si l'application est chargée (le parseur est aussi utilisé hors conteneur, en test unitaire). */
    private function t(string $key, array $replace = []): string
    {
        $text = function_exists('app') && app()->bound('translator') ? __($key, $replace) : $key;
        if ($text === $key) {
            foreach ($replace as $k => $v) {
                $text = str_replace(':' . $k, (string) $v, $text);
            }
        }

        return $text;
    }

    private function dayName(int $dow): string
    {
        return array_search($dow, self::DAYS, true) ?: '';
    }
}
