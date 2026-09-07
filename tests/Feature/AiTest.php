<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Place;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** IA : relève Gemini → Groq, recherche par envie, compagnon, audioguide génératif, replis sans clé. */
class AiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function gemini(string $text, int $status = 200)
    {
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]], $status);
    }

    private function openai(string $text, int $status = 200)
    {
        return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]]], $status);
    }

    public function test_without_any_key_the_ai_is_disabled_and_endpoints_fall_back(): void
    {
        config(['camino.ai.gemini_key' => '', 'camino.ai.groq_key' => '', 'camino.ai.mistral_key' => '']);
        $category = Category::create(['name' => 'Musée', 'slug' => 'musee']);
        $place = Place::create(['title' => 'Musée test', 'lat' => 48.86, 'lng' => 2.34, 'category_id' => $category->id, 'description' => 'Un musée de test avec une belle collection.', 'status' => 'approved']);

        $this->getJson('/api/v1/ai/status')->assertOk()->assertJson(['enabled' => false, 'providers' => []]);
        $this->getJson('/api/v1/ai/intent?q=musée gratuit ouvert maintenant')->assertOk()->assertJson(['ok' => false]);
        $this->getJson('/api/v1/ai/narration/' . $place->id)->assertOk()->assertJson(['ok' => true, 'source' => 'description', 'text' => 'Un musée de test avec une belle collection.']);
        $this->postJson('/api/v1/ai/compagnon', ['messages' => [['role' => 'user', 'content' => 'Un café ?']]])->assertStatus(503);
        $this->get('/carte')->assertOk()->assertSee('apiIntent\u0022:null', false);
    }

    public function test_gemini_answers_first_and_groq_takes_over_when_gemini_fails(): void
    {
        config(['camino.ai.gemini_key' => 'g-test', 'camino.ai.groq_key' => 'q-test', 'camino.ai.mistral_key' => '']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()->push(['candidates' => [['content' => ['parts' => [['text' => 'Réponse Gemini']]]]]])->push(['error' => ['message' => 'quota']], 429),
            'api.groq.com/*' => Http::sequence()->push(['choices' => [['message' => ['content' => 'Réponse Groq']]]])->push(['choices' => [['message' => ['content' => 'Encore Groq']]]]),
        ]);
        $ai = app(AiService::class);
        $this->assertSame(['gemini', 'groq'], $ai->status());
        $this->assertSame('Réponse Gemini', $ai->chat('sys', [['role' => 'user', 'content' => 'salut']]));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-2.5-flash:generateContent') && $request->hasHeader('x-goog-api-key', 'g-test') && $request['system_instruction']['parts'][0]['text'] === 'sys');

        // Gemini répond 429 : Groq prend le relais.
        $this->assertSame('Réponse Groq', $ai->chat('sys', [['role' => 'user', 'content' => 'salut']]));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.groq.com') && $request->hasHeader('Authorization', 'Bearer q-test') && $request['messages'][0]['role'] === 'system');

        // Gemini est mis de côté une minute : l'appel suivant va directement à Groq (2 appels Gemini + 2 appels Groq au total).
        $this->assertSame('Encore Groq', $ai->chat('sys', [['role' => 'user', 'content' => 'salut']]));
        Http::assertSentCount(4);
    }

    public function test_intent_search_turns_a_sentence_into_map_filters(): void
    {
        config(['camino.ai.gemini_key' => 'g-test']);
        Category::create(['name' => 'Musée', 'slug' => 'musee']);
        Category::create(['name' => 'Parc / Jardin', 'slug' => 'parc-jardin']);
        Http::fake(['generativelanguage.googleapis.com/*' => $this->gemini('```json{"category_slugs":["musee","inconnu"],"free":true,"open_now":true,"near":false,"events":false,"terms":"Marais","answer":"Musées gratuits ouverts dans le Marais"}```')]);

        $this->getJson('/api/v1/ai/intent?q=un musée gratuit ouvert maintenant dans le Marais')->assertOk()
            ->assertJson(['ok' => true, 'category_slugs' => ['musee'], 'free' => true, 'open_now' => true, 'terms' => 'Marais', 'answer' => 'Musées gratuits ouverts dans le Marais']);
        // Mis en cache : la deuxième demande n'appelle plus l'IA.
        $this->getJson('/api/v1/ai/intent?q=un musée gratuit ouvert maintenant dans le Marais')->assertOk()->assertJson(['ok' => true]);
        Http::assertSentCount(1);
        $this->get('/carte')->assertOk()->assertSee('apiIntent\u0022:\u0022http', false);
    }

    public function test_companion_uses_nearby_places_as_context(): void
    {
        config(['camino.ai.gemini_key' => 'g-test']);
        $category = Category::create(['name' => 'Restauration', 'slug' => 'restauration']);
        Place::create(['title' => 'Café des Arts', 'lat' => 48.8605, 'lng' => 2.3405, 'category_id' => $category->id, 'is_free' => false, 'status' => 'approved', 'address' => '1 rue des Arts']);
        Place::create(['title' => 'Bar caché', 'lat' => 48.8605, 'lng' => 2.3405, 'category_id' => $category->id, 'status' => 'pending']);
        Http::fake(['generativelanguage.googleapis.com/*' => $this->gemini('Le Café des Arts est à 60 m, parfait pour une pause.')]);

        $this->postJson('/api/v1/ai/compagnon', [
            'messages' => [['role' => 'user', 'content' => 'Un café près d\'ici ?']],
            'context' => ['title' => 'Balade test', 'steps' => [['title' => 'Musée test', 'arrive' => '10:20']], 'current' => 0, 'lat' => 48.86, 'lng' => 2.34, 'time' => '10:05'],
        ])->assertOk()->assertJson(['ok' => true, 'answer' => 'Le Café des Arts est à 60 m, parfait pour une pause.']);
        Http::assertSent(function ($request) {
            $system = $request['system_instruction']['parts'][0]['text'];

            return str_contains($system, 'Café des Arts') && ! str_contains($system, 'Bar caché') && str_contains($system, 'Musée test') && str_contains($system, 'étape en cours');
        });
        $this->postJson('/api/v1/ai/compagnon', ['messages' => []])->assertStatus(422);
    }

    public function test_generated_narration_is_cached_per_locale_and_falls_back_on_failure(): void
    {
        config(['camino.ai.gemini_key' => 'g-test']);
        $category = Category::create(['name' => 'Monument', 'slug' => 'monument']);
        $place = Place::create(['title' => 'Tour test', 'lat' => 48.86, 'lng' => 2.34, 'category_id' => $category->id, 'description' => 'Une tour de test construite pour les visiteurs curieux.', 'status' => 'approved']);
        $story = str_repeat('Devant toi se dresse la tour de test, un lieu plein de surprises. ', 3);
        // Séquence : le récit, puis une panne (le 2e appel FR est servi par le cache et ne consomme rien).
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()->push(['candidates' => [['content' => ['parts' => [['text' => $story]]]]]])->push(null, 500), '*' => Http::response(null, 503)]);

        $this->getJson('/api/v1/ai/narration/' . $place->id)->assertOk()->assertJson(['ok' => true, 'source' => 'ai', 'text' => trim($story)]);
        $this->getJson('/api/v1/ai/narration/' . $place->id)->assertOk()->assertJson(['source' => 'ai']);
        Http::assertSentCount(1);

        $this->getJson('/api/v1/ai/narration/' . $place->id . '?lang=en')->assertOk()->assertJson(['source' => 'description', 'text' => 'Une tour de test construite pour les visiteurs curieux.']);
        $this->getJson('/api/v1/ai/narration/999999')->assertStatus(404);
    }

    public function test_guidance_and_result_pages_expose_the_companion_only_when_ai_is_enabled(): void
    {
        $result = [
            'version' => 3, 'title' => 'Balade test', 'mode' => 'walk', 'start' => ['lat' => 48.8566, 'lng' => 2.3522, 'label' => 'Départ'], 'end' => null,
            'total_minutes' => 120, 'total_distance_km' => 2.5, 'total_cost_eur' => 0, 'starts_at' => '2026-09-06T10:00:00+02:00', 'ends_at' => '2026-09-06T12:00:00+02:00',
            'steps' => [['order' => 1, 'kind' => 'visit', 'place_id' => 1, 'title' => 'Musée test', 'cover' => null, 'category' => 'Musée', 'category_slug' => 'musee', 'lat' => 48.86, 'lng' => 2.34, 'visit_minutes' => 60, 'arrive_at' => '10:20', 'leave_at' => '11:20', 'travel_minutes' => 20, 'travel_km' => 1.2, 'is_free' => true, 'cost_eur' => 0, 'hours' => null]],
            'geometry' => [[48.8566, 2.3522], [48.86, 2.34]], 'legs' => [], 'warnings' => [],
        ];
        config(['camino.ai.gemini_key' => '', 'camino.ai.groq_key' => '']);
        $this->withSession(['itinerary_result' => $result])->get('/parcours/suivre')->assertOk()->assertDontSee('aiCompanion')->assertSee('\u0022ai\u0022:null', false);

        config(['camino.ai.gemini_key' => 'g-test']);
        $this->withSession(['itinerary_result' => $result])->get('/parcours/suivre')->assertOk()->assertSee('aiCompanion')->assertSee('\u0022ai\u0022:\u0022http', false);
    }
}
