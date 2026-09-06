<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Traduction automatique des textes des lieux (descriptions DATAtourisme, en français).
 *
 * Fournisseurs, dans l'ordre : DeepL (si une clé est configurée, qualité maximale),
 * puis MyMemory (API publique gratuite, sans clé, quota journalier plus large avec une adresse e-mail de contact).
 * Le résultat est mis en cache en base (table place_translations) : chaque texte n'est traduit qu'une fois par langue.
 */
class TranslationService
{
    private const MYMEMORY_CHUNK = 480;   // limite de 500 caractères par requête

    public function enabled(): bool
    {
        return (bool) config('camino.translation.enabled', true);
    }

    /** Fournisseur effectivement utilisé (pour l'affichage et les tests). */
    public function provider(): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return (string) config('camino.translation.deepl_key') !== '' ? 'deepl' : 'mymemory';
    }

    /**
     * Traduit un texte du français vers $to ('en', 'zh'). Retourne null si aucun fournisseur ne répond.
     */
    public function translate(string $text, string $to, string $from = 'fr'): ?string
    {
        $text = trim($text);
        if ($text === '' || ! $this->enabled() || $to === $from) {
            return $text === '' ? null : $text;
        }
        if (mb_strlen($text) > (int) config('camino.translation.max_chars', 4000)) {
            $text = mb_substr($text, 0, (int) config('camino.translation.max_chars', 4000));
        }
        $result = null;
        if ((string) config('camino.translation.deepl_key') !== '') {
            $result = $this->deepl($text, $to, $from);
        }
        if ($result === null) {
            $result = $this->myMemory($text, $to, $from);
        }

        return $result;
    }

    private function deepl(string $text, string $to, string $from): ?string
    {
        $key = (string) config('camino.translation.deepl_key');
        $base = str_ends_with($key, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';
        try {
            $response = Http::timeout((int) config('camino.translation.timeout', 8))
                ->withHeaders(['Authorization' => 'DeepL-Auth-Key ' . $key, 'User-Agent' => config('camino.user_agent')])
                ->asForm()
                ->post($base . '/v2/translate', [
                    'text' => $text,
                    'source_lang' => strtoupper($from),
                    'target_lang' => $to === 'zh' ? 'ZH' : strtoupper($to),
                    'preserve_formatting' => 1,
                ]);
            if ($response->ok()) {
                $t = $response->json('translations.0.text');

                return is_string($t) && trim($t) !== '' ? trim($t) : null;
            }
            Log::warning('DeepL error: HTTP ' . $response->status());
        } catch (\Throwable $e) {
            Log::warning('DeepL unavailable: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * MyMemory : 500 caractères par appel, on découpe sur les phrases et les paragraphes.
     */
    private function myMemory(string $text, string $to, string $from): ?string
    {
        $pair = $from . '|' . ($to === 'zh' ? 'zh-CN' : $to);
        $out = [];
        foreach (preg_split("/(\r?\n)/", $text, -1, PREG_SPLIT_DELIM_CAPTURE) as $piece) {
            if (preg_match("/^\r?\n$/", $piece)) {
                $out[] = $piece;

                continue;
            }
            if (trim($piece) === '') {
                $out[] = $piece;

                continue;
            }
            foreach ($this->chunks($piece, self::MYMEMORY_CHUNK) as $chunk) {
                $translated = $this->myMemoryCall($chunk, $pair);
                if ($translated === null) {
                    return null;
                }
                $out[] = $translated . ' ';
            }
            $out[count($out) - 1] = rtrim($out[count($out) - 1]);
        }

        return trim(implode('', $out)) ?: null;
    }

    private function myMemoryCall(string $chunk, string $pair): ?string
    {
        $query = ['q' => $chunk, 'langpair' => $pair];
        if ((string) config('camino.translation.email') !== '') {
            $query['de'] = config('camino.translation.email');
        }
        try {
            $response = Http::timeout((int) config('camino.translation.timeout', 8))
                ->withHeaders(['User-Agent' => config('camino.user_agent')])
                ->get('https://api.mymemory.translated.net/get', $query);
            if (! $response->ok()) {
                Log::warning('MyMemory error: HTTP ' . $response->status());

                return null;
            }
            $status = (int) $response->json('responseStatus', 0);
            $t = $response->json('responseData.translatedText');
            if ($status !== 200 || ! is_string($t) || trim($t) === '' || str_contains(strtoupper($t), 'MYMEMORY WARNING')) {
                Log::warning('MyMemory refused: ' . substr((string) ($response->json('responseDetails') ?? $t), 0, 120));

                return null;
            }

            return html_entity_decode(trim($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } catch (\Throwable $e) {
            Log::warning('MyMemory unavailable: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Découpe un paragraphe en morceaux de $max caractères au plus, sur les fins de phrase.
     *
     * @return array<int,string>
     */
    private function chunks(string $paragraph, int $max): array
    {
        $sentences = preg_split('/(?<=[.!?…])\s+/u', trim($paragraph)) ?: [trim($paragraph)];
        $chunks = [];
        $current = '';
        foreach ($sentences as $sentence) {
            if (mb_strlen($sentence) > $max) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                foreach (mb_str_split($sentence, $max) as $part) {
                    $chunks[] = $part;
                }

                continue;
            }
            if ($current !== '' && mb_strlen($current) + 1 + mb_strlen($sentence) > $max) {
                $chunks[] = $current;
                $current = $sentence;
            } else {
                $current = $current === '' ? $sentence : $current . ' ' . $sentence;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
