<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Place;
use App\Models\PlaceTranslation;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function place(string $description = "Un musée discret. Ses collections racontent Paris.\n\nOuvert tous les jours."): Place
    {
        $category = Category::create(['name' => 'Musée', 'slug' => 'musee']);

        return Place::create(['title' => 'Musée Test', 'lat' => 48.86, 'lng' => 2.35, 'category_id' => $category->id, 'description' => $description, 'is_free' => true, 'status' => 'published']);
    }

    public function test_mymemory_translation_is_chunked_and_cached(): void
    {
        config(['camino.translation.deepl_key' => '', 'camino.translation.email' => '']);
        Http::fake(['api.mymemory.translated.net/*' => function ($request) {
            $q = $request['q'] ?? '';

            return Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => 'EN:' . $q]], 200);
        }]);
        $place = $this->place();

        $text = $place->translatedDescription('en');

        $this->assertSame("EN:Un musée discret. Ses collections racontent Paris.\n\nEN:Ouvert tous les jours.", $text);
        $this->assertDatabaseHas('place_translations', ['place_id' => $place->id, 'locale' => 'en', 'provider' => 'mymemory']);
        $calls = fn () => Http::recorded(fn ($request) => str_contains($request->url(), 'mymemory'))->count();
        $this->assertSame(2, $calls());

        // Deuxième appel : lecture du cache, aucun appel réseau supplémentaire.
        $again = $place->fresh()->translatedDescription('en');
        $this->assertSame($text, $again);
        $this->assertSame(2, $calls());
    }

    public function test_deepl_is_preferred_when_a_key_is_set(): void
    {
        config(['camino.translation.deepl_key' => 'abc:fx']);
        Http::fake(['api-free.deepl.com/*' => Http::response(['translations' => [['text' => '这是一个博物馆。']]], 200)]);

        $text = app(TranslationService::class)->translate('Ceci est un musée.', 'zh');

        $this->assertSame('这是一个博物馆。', $text);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api-free.deepl.com') && $r['target_lang'] === 'ZH');
    }

    public function test_place_page_shows_translation_and_listen_button(): void
    {
        config(['camino.translation.deepl_key' => '']);
        $place = $this->place();
        PlaceTranslation::create(['place_id' => $place->id, 'locale' => 'en', 'field' => 'description', 'text' => 'A discreet museum.', 'provider' => 'test']);
        Http::fake(['*' => Http::response(null, 503)]);

        $this->withHeader('Accept-Language', 'en')->get('/lieux/' . $place->id . '?lang=en')
            ->assertOk()
            ->assertSee('A discreet museum.')
            ->assertSee('Automatically translated from French.')
            ->assertSee('placeReader(');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'mymemory') || str_contains($r->url(), 'deepl'));
    }

    public function test_place_page_falls_back_to_french_when_translation_fails(): void
    {
        config(['camino.translation.deepl_key' => '']);
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 429, 'responseDetails' => 'quota'], 200), '*' => Http::response(null, 503)]);
        $place = $this->place('Texte français.');

        $this->get('/lieux/' . $place->id . '?lang=en')->assertOk()->assertSee('Texte français.')->assertSee('French description');
        $this->assertDatabaseCount('place_translations', 0);
    }

    public function test_french_page_never_calls_the_translator(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
        $place = $this->place('Texte français.');
        $this->get('/lieux/' . $place->id . '?lang=fr')->assertOk()->assertSee('Texte français.');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'mymemory') || str_contains($r->url(), 'deepl'));
    }
}
