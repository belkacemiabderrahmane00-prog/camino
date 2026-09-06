<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Place;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitTest extends TestCase
{
    use RefreshDatabase;

    private function place(): Place
    {
        $cat = Category::create(['name' => 'Musée', 'slug' => 'musee']);

        return Place::create(['title' => 'Musée test', 'slug' => 'musee-test', 'category_id' => $cat->id, 'lat' => 48.85, 'lng' => 2.35, 'status' => 'approved', 'is_free' => true, 'external_id' => 'v1']);
    }

    public function test_guidance_records_a_visit_once_per_hour(): void
    {
        $user = User::factory()->create();
        $place = $this->place();

        $this->actingAs($user)->postJson("/lieux/{$place->id}/visite", ['source' => 'guidage', 'minutes' => 45])->assertOk()->assertJsonPath('recorded', true);
        $this->actingAs($user)->postJson("/lieux/{$place->id}/visite", ['source' => 'guidage'])->assertOk()->assertJsonPath('recorded', false);

        $this->assertSame(1, Visit::count());
        $this->assertSame('guidage', Visit::first()->source);
    }

    public function test_manual_visit_from_place_page_feeds_profile_and_stats(): void
    {
        $user = User::factory()->create();
        $place = $this->place();

        $this->actingAs($user)->post("/lieux/{$place->id}/visite", ['source' => 'manuel'])->assertRedirect();
        $this->assertSame(1, $user->visits()->count());

        $profile = app(\App\Services\UserPreferenceService::class)->profile($user->fresh());
        $this->assertSame('musee', $profile['top'][0]['slug'] ?? null);
        $this->assertSame(1, $profile['signals']['visits']);

        $stats = app(\App\Services\UserStatsService::class)->stats($user->fresh());
        $this->assertSame(1, $stats['visits']);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Musée test');
    }

    public function test_guests_cannot_record_visits(): void
    {
        $place = $this->place();
        $this->post("/lieux/{$place->id}/visite")->assertRedirect(route('login'));
    }
}
