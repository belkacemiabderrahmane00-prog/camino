<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IA générative gratuite avec relève automatique : Gemini (Google AI Studio) d'abord, puis Groq, puis Mistral.
 * Les clés ne vivent que dans les variables d'environnement (GEMINI_API_KEY, GROQ_API_KEY, MISTRAL_API_KEY).
 * Un fournisseur en erreur (quota, panne) est mis de côté une minute, le suivant prend le relais.
 */
class AiService
{
    private const DOWN_SECONDS = 60;

    /** @return array<int, array{name:string,key:string,model:string,url:string}> fournisseurs configurés, dans l'ordre d'essai */
    public function providers(): array
    {
        $cfg = config('camino.ai');
        $all = [
            'gemini' => ['name' => 'gemini', 'key' => (string) ($cfg['gemini_key'] ?? ''), 'model' => (string) ($cfg['gemini_model'] ?? 'gemini-2.5-flash'), 'url' => 'https://generativelanguage.googleapis.com/v1beta/models/'],
            'groq' => ['name' => 'groq', 'key' => (string) ($cfg['groq_key'] ?? ''), 'model' => (string) ($cfg['groq_model'] ?? 'llama-3.3-70b-versatile'), 'url' => 'https://api.groq.com/openai/v1/chat/completions'],
            'mistral' => ['name' => 'mistral', 'key' => (string) ($cfg['mistral_key'] ?? ''), 'model' => (string) ($cfg['mistral_model'] ?? 'mistral-small-latest'), 'url' => 'https://api.mistral.ai/v1/chat/completions'],
        ];
        $order = array_filter(array_map('trim', explode(',', (string) ($cfg['order'] ?? 'gemini,groq,mistral'))));
        $out = [];
        foreach ($order as $name) {
            if (isset($all[$name]) && $all[$name]['key'] !== '') {
                $out[] = $all[$name];
            }
        }

        return $out;
    }

    public function enabled(): bool
    {
        return (bool) config('camino.ai.enabled', true) && $this->providers() !== [];
    }

    /** @return array<int, string> noms des fournisseurs configurés (jamais les clés) */
    public function status(): array
    {
        return array_column($this->providers(), 'name');
    }

    /**
     * Réponse texte. $messages : [['role' => 'user'|'assistant', 'content' => '…'], …].
     *
     * @param  array{json?:bool,max_tokens?:int,temperature?:float}  $options
     */
    public function chat(string $system, array $messages, array $options = []): ?string
    {
        foreach ($this->providers() as $provider) {
            $downKey = 'ai_down_' . $provider['name'];
            if (Cache::has($downKey)) {
                continue;
            }
            try {
                $text = $provider['name'] === 'gemini' ? $this->gemini($provider, $system, $messages, $options) : $this->openAiCompatible($provider, $system, $messages, $options);
                if ($text !== null && trim($text) !== '') {
                    return trim($text);
                }
            } catch (\Throwable $e) {
                Log::warning('AI provider failed: ' . $provider['name'] . ' — ' . $e->getMessage());
            }
            Cache::put($downKey, 1, self::DOWN_SECONDS);
        }

        return null;
    }

    /** Réponse JSON (objet), null si aucun fournisseur n'a répondu correctement. */
    public function json(string $system, string $user, array $options = []): ?array
    {
        $text = $this->chat($system, [['role' => 'user', 'content' => $user]], $options + ['json' => true, 'temperature' => 0.2]);
        if ($text === null) {
            return null;
        }
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
        $decoded = json_decode((string) $text, true);
        if (! is_array($decoded)) {
            $start = strpos((string) $text, '{');
            $end = strrpos((string) $text, '}');
            $decoded = $start !== false && $end !== false ? json_decode(substr((string) $text, $start, $end - $start + 1), true) : null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function gemini(array $provider, string $system, array $messages, array $options): ?string
    {
        $contents = array_map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => (string) $m['content']]]], $messages);
        $generation = ['temperature' => $options['temperature'] ?? 0.7, 'maxOutputTokens' => $options['max_tokens'] ?? 700];
        if (! empty($options['json'])) {
            $generation['responseMimeType'] = 'application/json';
        }
        // Gemini 2.5 : le « raisonnement » consomme le budget de sortie ; on le coupe pour des réponses courtes et rapides.
        if (str_contains($provider['model'], '2.5')) {
            $generation['thinkingConfig'] = ['thinkingBudget' => 0];
        }
        $response = Http::timeout((int) config('camino.ai.timeout', 25))
            ->withHeaders(['x-goog-api-key' => $provider['key']])
            ->post($provider['url'] . $provider['model'] . ':generateContent', [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents' => $contents,
                'generationConfig' => $generation,
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException('HTTP ' . $response->status() . ' ' . mb_substr($response->body(), 0, 200));
        }
        $parts = $response->json('candidates.0.content.parts') ?? [];

        return implode('', array_map(fn ($p) => (string) ($p['text'] ?? ''), $parts)) ?: null;
    }

    private function openAiCompatible(array $provider, string $system, array $messages, array $options): ?string
    {
        $payload = [
            'model' => $provider['model'],
            'messages' => array_merge([['role' => 'system', 'content' => $system]], array_map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => (string) $m['content']], $messages)),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 700,
        ];
        if (! empty($options['json'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        $response = Http::timeout((int) config('camino.ai.timeout', 25))->withToken($provider['key'])->post($provider['url'], $payload);
        if (! $response->successful()) {
            throw new \RuntimeException('HTTP ' . $response->status() . ' ' . mb_substr($response->body(), 0, 200));
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * Diagnostic : un appel minimal par fournisseur, avec le statut HTTP et le début de la réponse en cas d'erreur (jamais la clé).
     *
     * @return array<string, array{ok:bool,model:string,error:?string}>
     */
    public function probe(): array
    {
        $out = [];
        foreach ($this->providers() as $provider) {
            try {
                $text = $provider['name'] === 'gemini' ? $this->gemini($provider, 'Réponds par le mot OK.', [['role' => 'user', 'content' => 'OK ?']], ['max_tokens' => 32]) : $this->openAiCompatible($provider, 'Réponds par le mot OK.', [['role' => 'user', 'content' => 'OK ?']], ['max_tokens' => 32]);
                $out[$provider['name']] = ['ok' => $text !== null && trim($text) !== '', 'model' => $provider['model'], 'error' => $text === null ? 'empty' : null];
            } catch (\Throwable $e) {
                $out[$provider['name']] = ['ok' => false, 'model' => $provider['model'], 'error' => mb_substr(str_replace($provider['key'], '***', $e->getMessage()), 0, 300)];
            }
        }

        return $out;
    }

    /** Nom de la langue de l'interface, pour les consignes. */
    public static function languageName(?string $locale = null): string
    {
        return match ($locale ?? app()->getLocale()) {
            'en' => 'English',
            'zh' => '简体中文 (Simplified Chinese)',
            default => 'français (tutoiement, ton chaleureux)',
        };
    }
}
