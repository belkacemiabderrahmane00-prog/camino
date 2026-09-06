<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    private function itinerary(?User $user = null): Itinerary
    {
        $category = Category::create(['name' => 'Musée', 'slug' => 'musee']);
        $place = Place::create(['title' => 'Musée Carnavalet', 'lat' => 48.857, 'lng' => 2.362, 'category_id' => $category->id, 'description' => "L'histoire de Paris dans deux hôtels particuliers. Des collections immenses. Entrée libre.", 'is_free' => true, 'status' => 'published', 'address' => '23 rue de Sévigné, Paris', 'cover_image_url' => 'https://upload.wikimedia.org/wikipedia/commons/a/a1/Carnavalet.jpg']);
        $result = [
            'version' => 3, 'title' => 'Balade musée · Marais', 'mode' => 'walk', 'date' => '2026-09-06', 'starts_at' => '2026-09-06 10:00:00', 'ends_at' => '2026-09-06 12:10:00',
            'start' => ['lat' => 48.853, 'lng' => 2.35, 'label' => 'Hôtel de Ville'], 'end' => null, 'geometry' => [[48.853, 2.35], [48.857, 2.362]],
            'total_distance_km' => 1.4, 'total_minutes' => 130, 'total_cost_eur' => 0,
            'steps' => [['place_id' => $place->id, 'title' => $place->title, 'category' => 'Musée', 'category_slug' => 'musee', 'lat' => 48.857, 'lng' => 2.362, 'order' => 1, 'arrive_at' => '10:20', 'leave_at' => '11:50', 'visit_minutes' => 90, 'travel_minutes' => 20, 'travel_km' => 1.4, 'cover' => null, 'kind' => 'visit', 'reason' => 'Correspond à tes centres d\'intérêt', 'is_free' => true]],
        ];

        return Itinerary::create(['user_id' => $user?->id, 'name' => 'Balade musée · Marais', 'result_json' => $result, 'share_token' => 'abcdefabcdefabcdef12']);
    }

    public function test_owner_sees_the_magazine_journal(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
        $user = User::factory()->create();
        $itinerary = $this->itinerary($user);

        $this->actingAs($user)->get('/mes-parcours/' . $itinerary->id . '/carnet')
            ->assertOk()
            ->assertSee('Carnet de voyage')
            ->assertSee('Balade musée · Marais')
            ->assertSee('Musée Carnavalet')
            ->assertSee('deux hôtels particuliers')
            ->assertSee('En chiffres')
            ->assertSee('journal-map');
    }

    public function test_journal_is_private_to_its_owner_but_public_via_share_token(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $itinerary = $this->itinerary($owner);

        $this->actingAs($other)->get('/mes-parcours/' . $itinerary->id . '/carnet')->assertForbidden();
        $this->get('/p/abcdefabcdefabcdef12/carnet')->assertOk()->assertSee('Refaire ce parcours')->assertSee('Musée Carnavalet');
        $this->get('/p/inconnu/carnet')->assertNotFound();
    }

    public function test_guidance_page_carries_the_audioguide_narration(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
        $user = User::factory()->create();
        $itinerary = $this->itinerary($user);

        $this->actingAs($user)->get('/mes-parcours/' . $itinerary->id . '/suivre')
            ->assertOk()
            ->assertSee('Audioguide')
            ->assertSee('narration', false)
            ->assertSee('particuliers', false)
            ->assertSee('/carnet', false);
    }
}
