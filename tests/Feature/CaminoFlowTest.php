<?php

namespace Tests\Feature;

use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Parcours utilisateur principaux de CAMINO (visiteur et connecté).
 */
class CaminoFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoDataSeeder::class);
        Http::fake(['*' => Http::response(null, 503)]); // routage et météo indisponibles : estimations locales
    }

    public function test_guest_can_browse_map_and_place_pages(): void
    {
        $place = Place::approved()->firstOrFail();

        $this->get('/carte')->assertOk()->assertSee('id="camino-map"', false);
        $this->get('/lieux/' . $place->id)->assertOk()->assertSee($place->title);
        $this->get('/parcours')->assertOk()->assertSee('name="start_lat"', false);
    }

    public function test_api_filters_places(): void
    {
        $this->getJson('/api/v1/pois?free=1')
            ->assertOk()
            ->assertJsonPath('data.0.is_free', true);

        $this->getJson('/api/v1/pois?q=louvre')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Musée du Louvre']);

        $this->getJson('/api/v1/pois?category_slugs=parc-jardin')
            ->assertOk()
            ->assertJsonPath('data.0.category.slug', 'parc-jardin');
    }

    public function test_hidden_places_are_not_exposed(): void
    {
        $hidden = Place::approved()->firstOrFail();
        $hidden->update(['status' => 'hidden']);

        $this->get('/lieux/' . $hidden->id)->assertOk(); // la fiche reste accessible par lien direct
        $this->getJson('/api/v1/poi/' . $hidden->id)->assertNotFound();
        $this->getJson('/api/v1/pois?q=' . urlencode($hidden->title))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_user_can_toggle_favorite_and_review(): void
    {
        $user = User::factory()->create();
        $place = Place::approved()->firstOrFail();

        $this->actingAs($user)->post('/lieux/' . $place->id . '/favori')->assertRedirect();
        $this->assertDatabaseHas('saved_places', ['user_id' => $user->id, 'place_id' => $place->id]);

        $this->actingAs($user)->post('/lieux/' . $place->id . '/favori')->assertRedirect();
        $this->assertDatabaseMissing('saved_places', ['user_id' => $user->id, 'place_id' => $place->id]);

        $this->actingAs($user)
            ->post('/lieux/' . $place->id . '/avis', ['rating' => 5, 'comment' => 'Superbe visite'])
            ->assertRedirect();
        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'place_id' => $place->id, 'rating' => 5]);
    }

    public function test_guest_cannot_favorite(): void
    {
        $place = Place::approved()->firstOrFail();

        $this->post('/lieux/' . $place->id . '/favori')->assertRedirect('/login');
    }

    public function test_generated_itinerary_is_saved_and_listed_for_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/parcours', ['duration_minutes' => 300, 'budget_eur' => 60, 'radius_km' => 15, 'interests' => ['musee', 'parc-jardin']])
            ->assertRedirect(route('itineraries.create'));

        $itinerary = Itinerary::where('user_id', $user->id)->firstOrFail();
        $this->assertNotEmpty($itinerary->result_json['steps']);
        $this->assertArrayHasKey('lat', $itinerary->result_json['steps'][0]);

        $this->actingAs($user)->get('/mes-parcours')->assertOk()->assertSee($itinerary->name);
        $this->actingAs($user)->get('/parcours')->assertOk()->assertSee('itinerary-map', false);
    }

    public function test_user_cannot_touch_someone_elses_itinerary(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $itinerary = Itinerary::create(['user_id' => $owner->id, 'name' => 'Privé', 'result_json' => ['steps' => []]]);

        $this->actingAs($other)->delete('/mes-parcours/' . $itinerary->id)->assertForbidden();
        $this->actingAs($other)->post('/mes-parcours/' . $itinerary->id . '/revoir')->assertForbidden();
        $this->actingAs($owner)->delete('/mes-parcours/' . $itinerary->id)->assertRedirect(route('itineraries.index'));
        $this->assertDatabaseMissing('itineraries', ['id' => $itinerary->id]);
    }

    public function test_place_report_is_stored(): void
    {
        $place = Place::approved()->firstOrFail();

        $this->post('/lieux/' . $place->id . '/signaler', ['reason' => 'Fermé définitivement'])->assertRedirect();
        $this->assertDatabaseHas('place_reports', ['place_id' => $place->id, 'reason' => 'Fermé définitivement']);
    }
}
