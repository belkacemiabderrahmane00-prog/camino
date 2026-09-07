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
class ModelGoneException extends \RuntimeException
{
}

class AiService
{
    private const DOWN_SECONDS = 60;

    /** @return array<int, array{name:string,key:string,model:string,url:string}> fournisseurs configurés, dans l'ordre d'essai */
    public function providers(): array
    {
        $cfg = config('camino.ai');
        $all = [
            'gemini' => ['name' => 'gemini', 'key' => (string) ($cfg['gemini_key'] ?? ''), 'model' => (string) ($cfg['gemini_model'] ?: 'gemini-3.6-flash'), 'url' => 'https://generativelanguage.googleapis.com/v1beta/models/'],
            'groq' => ['name' => 'groq', 'key' => (string) ($cfg['groq_key'] ?? ''), 'model' => (string) ($cfg['groq_model'] ?: 'llama-3.3-70b-versatile'), 'url' => 'https://api.groq.com/openai/v1/chat/completions'],
            'mistral' => ['name' => 'mistral', 'key' => (string) ($cfg['mistral_key'] ?? ''), 'model' => (string) ($cfg['mistral_model'] ?: 'mistral-small-latest'), 'url' => 'https://api.mistral.ai/v1/chat/completions'],
        ];
        $order = array_filter(array_map('trim', explode(',', (string) ($cfg['order'] ?? 'gemini,groq,mistral'))));
        $out = [];
        foreach ($order as $name) {
            if (isset($all[$name]) && $all[$name]['key'] !== '') {
                // Un modèle retiré par le fournisseur est remplacé par celui découvert (mémorisé une journée).
                $all[$name]['model'] = Cache::get('ai_model_' . $name, $all[$name]['model']);
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
                $text = $this->call($provider, $system, $messages, $options);
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

    /** Appel d'un fournisseur ; si le modèle n'existe plus, on en découvre un autre et on réessaie une fois. */
    private function call(array $provider, string $system, array $messages, array $options): ?string
    {
        try {
            return $provider['name'] === 'gemini' ? $this->gemini($provider, $system, $messages, $options) : $this->openAiCompatible($provider, $system, $messages, $options);
        } catch (ModelGoneException $e) {
            $model = $this->discoverModel($provider, $e->getMessage());
            if ($model === null || $model === $provider['model']) {
                throw $e;
            }
            Log::notice('AI model replaced: ' . $provider['name'] . ' ' . $provider['model'] . ' → ' . $model);
            Cache::put('ai_model_' . $provider['name'], $model, now()->addDay());
            $provider['model'] = $model;

            return $provider['name'] === 'gemini' ? $this->gemini($provider, $system, $messages, $options) : $this->openAiCompatible($provider, $system, $messages, $options);
        }
    }

    /** Modèle de remplacement : celui suggéré dans le message d'erreur, sinon le meilleur de la liste publiée par le fournisseur. */
    private function discoverModel(array $provider, string $error): ?string
    {
        // Le corps de l'erreur est du JSON : les barres obliques y sont parfois échappées (models\/gemini-…).
        if (preg_match_all('/models\\\\?\/(gemini-[a-z0-9.\-]+)/i', $error, $m)) {
            foreach (array_unique($m[1]) as $suggested) {
                if ($suggested !== $provider['model'] && ! str_contains($error, $suggested . ' is no longer')) {
                    return $suggested;
                }
            }
        }
        try {
            if ($provider['name'] === 'gemini') {
                $response = Http::timeout(15)->withHeaders(['x-goog-api-key' => $provider['key']])->get($provider['url'] . '?pageSize=200');
                $names = collect($response->json('models') ?? [])
                    ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
                    ->map(fn ($m) => (string) preg_replace('/^models\//', '', $m['name'] ?? ''));
                $pick = $this->pickModel($names->all(), ['flash'], ['preview', 'exp', 'lite', 'image', 'tts', 'live', 'audio', 'embedding', 'thinking', 'robotics', 'computer']);

                return $pick ?? $this->pickModel($names->all(), ['gemini'], ['embedding', 'image', 'tts', 'live', 'audio']);
            }
            $response = Http::timeout(15)->withToken($provider['key'])->get(str_replace('/chat/completions', '/models', $provider['url']));
            $ids = collect($response->json('data') ?? [])->map(fn ($m) => (string) ($m['id'] ?? ''))->all();
            if ($provider['name'] === 'groq') {
                return $this->pickModel($ids, ['llama'], ['guard', 'whisper', 'tts', 'vision', 'prompt', 'safeguard', '8b', '1b', '3b'])
                    ?? $this->pickModel($ids, ['llama', 'qwen', 'gpt', 'kimi', 'mixtral', 'gemma'], ['guard', 'whisper', 'tts', 'prompt']);
            }

            return $this->pickModel($ids, ['small', 'medium', 'large'], ['embed', 'ocr', 'moderation', 'codestral']) ?? ($ids[0] ?? null);
        } catch (\Throwable $e) {
            Log::warning('AI model discovery failed: ' . $provider['name'] . ' — ' . $e->getMessage());

            return null;
        }
    }

    /** Choisit le nom contenant l'un des mots voulus, sans les mots exclus, en préférant le numéro de version le plus élevé. */
    private function pickModel(array $names, array $wanted, array $excluded): ?string
    {
        $candidates = array_values(array_filter($names, function (string $n) use ($wanted, $excluded) {
            $l = strtolower($n);
            foreach ($excluded as $x) {
                if (str_contains($l, $x)) {
                    return false;
                }
            }
            foreach ($wanted as $w) {
                if (str_contains($l, $w)) {
                    return true;
                }
            }

            return false;
        }));
        usort($candidates, function (string $a, string $b) {
            preg_match('/(\d+(?:\.\d+)?)/', $a, $ma); preg_match('/(\d+(?:\.\d+)?)/', $b, $mb);
            $va = (float) ($ma[1] ?? 0); $vb = (float) ($mb[1] ?? 0);
            if ($va !== $vb) {
                return $vb <=> $va;
            }
            // À version égale : le plus gros (70b > 8b), puis le nom le plus court (version stable plutôt que datée).
            preg_match('/(\d+)b/i', $a, $sa); preg_match('/(\d+)b/i', $b, $sb);
            $wa = (int) ($sa[1] ?? 0); $wb = (int) ($sb[1] ?? 0);

            return $wa !== $wb ? $wb <=> $wa : strlen($a) <=> strlen($b);
        });

        return $candidates[0] ?? null;
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
            $this->throwFor($response);
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
            $this->throwFor($response);
        }

        return $response->json('choices.0.message.content');
    }

    private function throwFor(\Illuminate\Http\Client\Response $response): never
    {
        $body = mb_substr($response->body(), 0, 300);
        $gone = $response->status() === 404 || str_contains($body, 'model_not_found') || str_contains($body, 'no longer available') || str_contains($body, 'is not found');
        if ($gone) {
            throw new ModelGoneException('HTTP ' . $response->status() . ' ' . $body);
        }
        throw new \RuntimeException('HTTP ' . $response->status() . ' ' . $body);
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
                $text = $this->call($provider, 'Réponds par le mot OK.', [['role' => 'user', 'content' => 'OK ?']], ['max_tokens' => 32]);
                $out[$provider['name']] = ['ok' => $text !== null && trim($text) !== '', 'model' => Cache::get('ai_model_' . $provider['name'], $provider['model']), 'error' => $text === null ? 'empty' : null];
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
