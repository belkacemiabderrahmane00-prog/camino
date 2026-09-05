<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function sampleResult(): array
    {
        return [
            'version' => 3, 'title' => 'Balade test', 'mode' => 'walk',
            'start' => ['lat' => 48.8566, 'lng' => 2.3522, 'label' => 'Départ'], 'end' => null, 'loop' => false,
            'starts_at' => '2026-09-06T10:00:00+02:00', 'ends_at' => '2026-09-06T12:00:00+02:00',
            'total_minutes' => 120, 'total_distance_km' => 2.5, 'total_cost_eur' => 0,
            'steps' => [['order' => 1, 'kind' => 'visit', 'place_id' => 1, 'title' => 'Musée test', 'cover' => null, 'category' => 'Musée', 'category_slug' => 'musee', 'lat' => 48.86, 'lng' => 2.34, 'visit_minutes' => 60, 'arrive_at' => '10:20', 'leave_at' => '11:20', 'travel_minutes' => 20, 'travel_km' => 1.2, 'is_free' => true, 'cost_eur' => 0, 'hours' => ['status' => 'open', 'opens' => '10:00', 'closes' => '18:00', 'note' => null]]],
            'geometry' => [[48.8566, 2.3522], [48.86, 2.34]],
            'legs' => [['distance_km' => 1.2, 'duration_min' => 20, 'shape' => [[48.8566, 2.3522], [48.86, 2.34]], 'maneuvers' => [['type' => 1, 'text' => 'Marchez vers le nord.', 'verbal' => 'Marchez vers le nord.', 'street' => '', 'begin' => 0, 'end' => 1]]]],
            'routing_source' => 'valhalla', 'warnings' => [],
        ];
    }

    public function test_navigation_requires_a_generated_itinerary(): void
    {
        $this->get('/parcours/suivre')->assertRedirect(route('itineraries.create'));
    }

    public function test_navigation_page_renders_the_session_itinerary(): void
    {
        $this->withSession(['itinerary_result' => $this->sampleResult()])
            ->get('/parcours/suivre')
            ->assertOk()
            ->assertSee('Démarrer le guidage')
            ->assertSee('nav-map', false)
            ->assertSee('Marchez vers le nord.');
    }

    public function test_saved_itinerary_navigation_is_private(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $itinerary = $owner->itineraries()->create(['name' => 'Balade test', 'result_json' => $this->sampleResult()]);

        $this->actingAs($other)->get("/mes-parcours/{$itinerary->id}/suivre")->assertForbidden();
        $this->actingAs($owner)->get("/mes-parcours/{$itinerary->id}/suivre")->assertOk()->assertSee('Balade test');
    }

    public function test_reroute_api_returns_a_leg_with_shape(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);

        $this->postJson('/api/v1/route', ['points' => [['lat' => 48.8566, 'lng' => 2.3522], ['lat' => 48.86, 'lng' => 2.34]], 'mode' => 'walk'])
            ->assertOk()
            ->assertJsonPath('source', 'estimate')
            ->assertJsonCount(1, 'legs')
            ->assertJsonPath('legs.0.shape.0.0', 48.8566);
    }
}
