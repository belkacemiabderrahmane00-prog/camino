<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ItineraryEditingTest extends TestCase
{
    use RefreshDatabase;

    private array $places = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 503)]);
        $museum = Category::create(['name' => 'Musée', 'slug' => 'musee']);
        $park = Category::create(['name' => 'Parc / Jardin', 'slug' => 'parc-jardin']);
        $hours = ['periods' => [['from' => null, 'through' => null, 'opens' => '09:00', 'closes' => '19:00', 'days' => null, 'note' => null]], 'closed_days' => [], 'confidence' => 'structured'];
        foreach ([
            ['Musée A', $museum, 48.8566, 2.3522], ['Musée B', $museum, 48.8580, 2.3540], ['Musée C', $museum, 48.8590, 2.3500],
            ['Parc D', $park, 48.8600, 2.3450], ['Parc E', $park, 48.8620, 2.3480],
        ] as $i => [$title, $cat, $lat, $lng]) {
            $this->places[] = Place::create(['title' => $title, 'slug' => 'p' . $i, 'category_id' => $cat->id, 'lat' => $lat, 'lng' => $lng, 'status' => 'approved', 'is_free' => true, 'visit_duration_min' => 45, 'opening_hours' => $hours, 'external_id' => 'e' . $i]);
        }
    }

    private function generate(): array
    {
        $this->post('/parcours', ['duration_minutes' => 300, 'interests' => ['musee', 'parc-jardin'], 'radius_km' => 5, 'date' => now()->addDay()->format('Y-m-d'), 'starts_at' => '10:00', 'use_weather' => 0])
            ->assertRedirect(route('itineraries.create'));

        return session('itinerary_result');
    }

    public function test_generation_offers_variants_and_switching_keeps_the_session_consistent(): void
    {
        $result = $this->generate();
        $this->assertNotEmpty($result['steps']);
        $this->assertArrayHasKey('variants', $result);
        if (count($result['variants']) > 1) {
            $other = collect($result['variants'])->firstWhere('active', false);
            $this->post('/parcours/variante/' . $other['key'])->assertRedirect(route('itineraries.create'));
            $this->assertTrue(collect(session('itinerary_result')['variants'])->firstWhere('key', $other['key'])['active']);
        }
    }

    public function test_steps_can_be_removed_moved_and_extended(): void
    {
        $result = $this->generate();
        $count = count($result['steps']);
        $this->assertGreaterThanOrEqual(2, $count);

        $first = $result['steps'][0]['title'];
        $this->post('/parcours/etape/0/deplacer', ['direction' => 'down'])->assertRedirect();
        $moved = session('itinerary_result');
        $this->assertSame($first, $moved['steps'][1]['title']);
        $this->assertTrue($moved['edited']);

        $this->post('/parcours/etape/0/duree', ['delta' => 15])->assertRedirect();
        $this->assertSame(60, session('itinerary_result')['steps'][0]['visit_minutes']);

        $this->post('/parcours/etape/0/retirer')->assertRedirect();
        $this->assertCount($count - 1, session('itinerary_result')['steps']);
    }

    public function test_lock_and_replace(): void
    {
        $result = $this->generate();
        $this->post('/parcours/etape/0/verrouiller')->assertRedirect();
        $this->assertTrue(session('itinerary_result')['steps'][0]['locked']);
        $this->assertContains($result['steps'][0]['place_id'], session('itinerary_locked'));

        $before = session('itinerary_result')['steps'][1]['place_id'];
        $this->post('/parcours/etape/1/remplacer')->assertRedirect();
        $after = session('itinerary_result')['steps'][1]['place_id'];
        $this->assertNotSame($before, $after);
    }

    public function test_share_link_and_gpx(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $result = $this->generate();
        $itinerary = $user->itineraries()->firstOrFail();

        $this->post("/mes-parcours/{$itinerary->id}/partager")->assertRedirect();
        $itinerary->refresh();
        $this->assertNotNull($itinerary->share_token);

        $this->get('/p/' . $itinerary->share_token)->assertOk()->assertSee($itinerary->name)->assertSee('Ouvrir dans CAMINO');
        $this->get('/p/' . $itinerary->share_token . '/gpx')->assertOk()->assertHeader('Content-Type', 'application/gpx+xml; charset=utf-8')->assertSee('<trkpt', false);

        auth()->logout();
        $this->post('/p/' . $itinerary->share_token . '/ouvrir')->assertRedirect(route('itineraries.create'));
        $this->assertSame($itinerary->name, session('itinerary_result')['title']);
        $this->get('/p/inconnu')->assertNotFound();
    }
}
