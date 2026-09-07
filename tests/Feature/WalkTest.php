<?php

namespace Tests\Feature;

use App\Models\Itinerary;
use App\Models\User;
use App\Models\Walk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Balade à plusieurs : création, invités sans compte, positions, rendez-vous, messages, photos, fin. */
class WalkTest extends TestCase
{
    use RefreshDatabase;

    private function sampleResult(): array
    {
        return [
            'version' => 3, 'title' => 'Balade test', 'mode' => 'walk',
            'start' => ['lat' => 48.8566, 'lng' => 2.3522, 'label' => 'Départ'], 'end' => null,
            'total_minutes' => 120, 'total_distance_km' => 2.5, 'total_cost_eur' => 0,
            'steps' => [['order' => 1, 'kind' => 'visit', 'place_id' => 1, 'title' => 'Musée test', 'cover' => null, 'category' => 'Musée', 'category_slug' => 'musee', 'lat' => 48.86, 'lng' => 2.34, 'visit_minutes' => 60, 'arrive_at' => '10:20', 'leave_at' => '11:20', 'travel_minutes' => 20, 'travel_km' => 1.2, 'is_free' => true]],
            'geometry' => [[48.8566, 2.3522], [48.86, 2.34]],
            'legs' => [['distance_km' => 1.2, 'duration_min' => 20, 'shape' => [[48.8566, 2.3522], [48.86, 2.34]], 'maneuvers' => []]],
        ];
    }

    public function test_guest_creates_a_walk_from_the_session_itinerary_and_becomes_host(): void
    {
        $response = $this->withSession(['itinerary_result' => $this->sampleResult()])->post('/balades');

        $walk = Walk::first();
        $this->assertNotNull($walk);
        $response->assertRedirect('/b/' . $walk->code);
        $this->assertSame('Balade test', $walk->title);
        $this->assertCount(1, $walk->members);
        $this->assertSame($walk->members->first()->id, $walk->host_member_id);
        $this->assertSame([[48.8566, 2.3522], [48.86, 2.34]], $walk->route_json['geometry']);

        $this->get('/b/' . $walk->code)->assertOk()->assertSee('walkLive')->assertSee('Groupe');
    }

    public function test_newcomer_sees_the_join_form_then_joins_with_a_name_and_colour(): void
    {
        $walk = Walk::create(['code' => 'abc12345', 'title' => 'Balade test', 'status' => 'active', 'expires_at' => now()->addHours(12)]);

        $this->get('/b/abc12345')->assertOk()->assertSee('Rejoindre la balade')->assertDontSee('walkLive');

        $this->post('/b/abc12345/rejoindre', ['name' => 'Léa', 'color' => '#7C3AED'])->assertRedirect('/b/abc12345');
        $member = $walk->members()->first();
        $this->assertSame('Léa', $member->name);
        $this->assertSame('#7C3AED', $member->color);
        $this->assertSame('join', $walk->messages()->first()->type);

        $this->get('/b/abc12345')->assertOk()->assertSee('walkLive');
        $this->get('/b/abc12345/etat')->assertOk()->assertJsonPath('me', $member->id)->assertJsonPath('members.0.name', 'Léa')->assertJsonPath('messages.0.type', 'join');
    }

    public function test_state_is_private_to_members(): void
    {
        Walk::create(['code' => 'abc12345', 'title' => 'Balade test', 'status' => 'active', 'expires_at' => now()->addHours(12)]);

        $this->getJson('/b/abc12345/etat')->assertStatus(403);
        $this->postJson('/b/abc12345/message', ['type' => 'text', 'body' => 'Coucou'])->assertStatus(403);
        $this->getJson('/b/zzzzzzzz/etat')->assertStatus(404);
    }

    public function test_positions_messages_meeting_point_and_arrival(): void
    {
        Walk::create(['code' => 'abc12345', 'title' => 'Balade test', 'status' => 'active', 'expires_at' => now()->addHours(12)]);
        $this->post('/b/abc12345/rejoindre', ['name' => 'Léa']);

        $this->postJson('/b/abc12345/position', ['lat' => 48.8622, 'lng' => 2.3402, 'heading' => 45, 'accuracy' => 8])->assertOk()->assertJsonPath('arrived', false);
        $this->postJson('/b/abc12345/message', ['type' => 'text', 'body' => 'Je suis devant le Palais-Royal'])->assertOk();
        $this->postJson('/b/abc12345/message', ['type' => 'emoji', 'body' => '☕'])->assertOk();
        $this->postJson('/b/abc12345/message', ['type' => 'text', 'body' => ''])->assertStatus(422);
        $this->postJson('/b/abc12345/message', ['type' => 'video', 'body' => 'x'])->assertStatus(422);

        // Rendez-vous posé à 30 m : la position suivante déclenche l'arrivée, annoncée au groupe.
        $this->postJson('/b/abc12345/rendez-vous', ['lat' => 48.8624, 'lng' => 2.3405, 'label' => 'Colonnes de Buren'])->assertOk();
        $this->postJson('/b/abc12345/position', ['lat' => 48.8624, 'lng' => 2.3405])->assertOk()->assertJsonPath('arrived', true);

        $state = $this->getJson('/b/abc12345/etat')->assertOk()->json();
        $this->assertSame('Colonnes de Buren', $state['meeting']['label']);
        $this->assertTrue($state['members'][0]['arrived']);
        $this->assertSame(['join', 'text', 'emoji', 'meet', 'arrive'], array_column($state['messages'], 'type'));
        $this->assertSame('Je suis devant le Palais-Royal', $state['messages'][1]['body']);

        // Lecture incrémentale + annulation du rendez-vous.
        $this->getJson('/b/abc12345/etat?since=' . $state['messages'][3]['id'])->assertOk()->assertJsonCount(1, 'messages');
        $this->deleteJson('/b/abc12345/rendez-vous')->assertOk();
        $this->getJson('/b/abc12345/etat')->assertJsonPath('meeting', null)->assertJsonPath('members.0.arrived', false);
    }

    public function test_photo_is_resized_and_served_to_the_group(): void
    {
        Walk::create(['code' => 'abc12345', 'title' => 'Balade test', 'status' => 'active', 'expires_at' => now()->addHours(12)]);
        $this->post('/b/abc12345/rejoindre', ['name' => 'Léa']);

        $response = $this->post('/b/abc12345/photo', ['photo' => UploadedFile::fake()->image('balade.jpg', 2400, 1600)], ['Accept' => 'application/json'])->assertOk();
        $url = $response->json('url');
        $this->assertStringContainsString('/b/abc12345/photo/', $url);

        $photo = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $size = getimagesizefromstring($photo->getContent());
        $this->assertSame(1400, $size[0]);
        $this->assertSame('photo', Walk::first()->messages()->latest('id')->first()->type);
    }

    public function test_logged_in_user_joins_automatically_and_host_can_end_the_walk(): void
    {
        $user = User::factory()->create(['name' => 'Khalef']);
        $itinerary = Itinerary::create(['user_id' => $user->id, 'name' => 'Balade test', 'result_json' => $this->sampleResult()]);

        $this->actingAs($user)->post('/balades', ['itinerary_id' => $itinerary->id]);
        $walk = Walk::first();
        $this->assertSame($itinerary->id, $walk->itinerary_id);
        $this->assertSame($user->id, $walk->members->first()->user_id);

        // Un autre utilisateur connecté entre directement, sans formulaire.
        $friend = User::factory()->create(['name' => 'Sam']);
        $this->actingAs($friend)->get('/b/' . $walk->code)->assertOk()->assertSee('walkLive');
        $this->assertCount(2, $walk->fresh()->members);

        // L'ami ne peut pas terminer, l'hôte oui.
        $this->actingAs($friend)->post('/b/' . $walk->code . '/terminer')->assertStatus(403);
        $this->actingAs($user)->post('/b/' . $walk->code . '/terminer')->assertRedirect('/b/' . $walk->code);
        $this->assertSame('ended', $walk->fresh()->status);
        $this->actingAs($friend)->get('/b/' . $walk->code)->assertOk()->assertSee('Cette balade est termin');
        $this->actingAs($friend)->getJson('/b/' . $walk->code . '/etat')->assertOk()->assertJsonPath('walk.status', 'ended');
    }

    public function test_guidance_shows_the_walk_when_the_visitor_is_a_member(): void
    {
        Walk::create(['code' => 'abc12345', 'title' => 'Balade test', 'status' => 'active', 'expires_at' => now()->addHours(12)]);
        $this->withSession(['itinerary_result' => $this->sampleResult()])->post('/b/abc12345/rejoindre', ['name' => 'Léa']);

        $this->get('/parcours/suivre?balade=abc12345')->assertOk()->assertSee('Tes amis appara')->assertSee('Balade à plusieurs');
        $this->flushSession();
        $this->withSession(['itinerary_result' => $this->sampleResult()])->get('/parcours/suivre?balade=abc12345')->assertOk()->assertDontSee('Tes amis appara')->assertSee('À plusieurs');
    }

    public function test_leaving_removes_the_member_from_the_group(): void
    {
        Walk::create(['code' => 'abc12345', 'title' => 'Balade test', 'status' => 'active', 'expires_at' => now()->addHours(12)]);
        $this->post('/b/abc12345/rejoindre', ['name' => 'Léa']);
        $this->post('/b/abc12345/quitter')->assertRedirect('/');

        $this->assertNotNull(Walk::first()->members()->first()->left_at);
        $this->get('/b/abc12345')->assertOk()->assertSee('Rejoindre la balade');
    }
}
