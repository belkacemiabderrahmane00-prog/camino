<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Place;
use App\Services\ItineraryGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Le routage et la météo sont simulés indisponibles : le générateur doit retomber
 * sur les estimations à vol d'oiseau sans planter.
 */
class ItineraryGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 503)]);
    }

    private function place(int $id, string $title, float $lat, float $lng, bool $free, ?int $level, int $visit, string $slug = 'musee'): Place
    {
        $category = new Category(['name' => ucfirst($slug), 'slug' => $slug]);
        $place = new Place();
        $place->forceFill(['id' => $id, 'title' => $title, 'lat' => $lat, 'lng' => $lng, 'is_free' => $free, 'price_level' => $level, 'visit_duration_min' => $visit]);
        $place->setRelation('category', $category);

        return $place;
    }

    public function test_generate_returns_empty_result_when_no_places(): void
    {
        $result = app(ItineraryGenerator::class)->generate(new Collection(), ['time_budget_min' => 120, 'use_weather' => false]);

        $this->assertSame(0, $result['total_minutes']);
        $this->assertSame([], $result['steps']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_generate_respects_free_only_and_budget(): void
    {
        $free = $this->place(1, 'Free Place', 48.8566, 2.3522, true, null, 30);
        $paid = $this->place(2, 'Paid Place', 48.8570, 2.3530, false, 2, 30);

        $result = app(ItineraryGenerator::class)->generate(new Collection([$free, $paid]), [
            'time_budget_min' => 120,
            'free_only' => true,
            'budget_eur' => 10.0,
            'start' => ['lat' => 48.8566, 'lng' => 2.3522],
            'use_weather' => false,
        ]);

        $this->assertCount(1, $result['steps']);
        $this->assertSame(0.0, $result['total_cost_eur']);
        $this->assertSame('Free Place', $result['steps'][0]['title']);
        $this->assertSame('estimate', $result['routing_source']);
    }

    public function test_generate_estimates_cost_and_schedule(): void
    {
        $a = $this->place(3, 'Paid Place', 48.8566, 2.3522, false, 3, 30);
        $b = $this->place(4, 'Park', 48.8600, 2.3400, true, null, 45, 'parc-jardin');

        $result = app(ItineraryGenerator::class)->generate(collect([$a, $b]), [
            'time_budget_min' => 240,
            'start' => ['lat' => 48.8566, 'lng' => 2.3522],
            'use_weather' => false,
        ]);

        $this->assertCount(2, $result['steps']);
        $this->assertSame(30.0, $result['total_cost_eur']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result['steps'][0]['arrive_at']);
        $this->assertGreaterThan(0, $result['steps'][1]['travel_minutes']);
        $this->assertNotEmpty($result['geometry']);
    }

    public function test_interests_drive_selection(): void
    {
        $museum = $this->place(5, 'Museum', 48.8566, 2.3522, true, null, 60, 'musee');
        $park = $this->place(6, 'Park', 48.8568, 2.3525, true, null, 60, 'parc-jardin');

        $result = app(ItineraryGenerator::class)->generate(collect([$park, $museum]), [
            'time_budget_min' => 90,
            'interests' => ['musee'],
            'start' => ['lat' => 48.8566, 'lng' => 2.3522],
            'use_weather' => false,
        ]);

        $this->assertSame('Museum', $result['steps'][0]['title']);
    }
    public function test_closed_places_are_skipped_and_open_ones_scheduled(): void
    {
        $open = $this->place(7, 'Open Museum', 48.8566, 2.3522, true, null, 60);
        $open->forceFill(['opening_hours' => ['periods' => [['from' => null, 'through' => null, 'opens' => '10:00', 'closes' => '18:00', 'days' => null, 'note' => null]], 'closed_days' => [], 'confidence' => 'structured']]);
        $closed = $this->place(8, 'Closed Museum', 48.8568, 2.3525, true, null, 60);
        $closed->forceFill(['opening_hours' => ['periods' => [['from' => null, 'through' => null, 'opens' => '10:00', 'closes' => '18:00', 'days' => null, 'note' => null]], 'closed_days' => [2], 'confidence' => 'structured']]);

        // Mardi 8 septembre 2026, départ 9 h 45 : le musée fermé le mardi est écarté, l'autre attend l'ouverture.
        $result = app(ItineraryGenerator::class)->generate(collect([$closed, $open]), [
            'time_budget_min' => 180,
            'start' => ['lat' => 48.8566, 'lng' => 2.3522],
            'starts_at' => \Illuminate\Support\Carbon::parse('2026-09-08 09:45', config('app.timezone')),
            'use_weather' => false,
        ]);

        $this->assertCount(1, $result['steps']);
        $this->assertSame('Open Museum', $result['steps'][0]['title']);
        $this->assertSame(15, $result['steps'][0]['wait_minutes']);
        $this->assertSame('10:00', $result['steps'][0]['start_visit_at']);
        $this->assertSame('open', $result['steps'][0]['hours']['status']);
        $this->assertSame(3, $result['version']);
        $this->assertStringContainsString('fermé', implode(' ', $result['warnings']));
    }

    public function test_loop_returns_to_start_and_counts_the_way_back(): void
    {
        $a = $this->place(9, 'Far Park', 48.8700, 2.3400, true, null, 45, 'parc-jardin');
        $b = $this->place(10, 'Near Monument', 48.8570, 2.3530, true, null, 45, 'monument');

        $result = app(ItineraryGenerator::class)->generate(collect([$a, $b]), [
            'time_budget_min' => 240,
            'start' => ['lat' => 48.8566, 'lng' => 2.3522, 'label' => 'Maison'],
            'loop' => true,
            'use_weather' => false,
        ]);

        $this->assertTrue($result['loop']);
        $this->assertSame('Retour au départ', $result['end']['label']);
        $this->assertGreaterThan(0, $result['end']['travel_minutes']);
        $this->assertSame($result['travel_minutes'] + $result['visit_minutes'] + $result['wait_minutes'], $result['total_minutes']);
        $this->assertLessThanOrEqual(240, $result['total_minutes']);
    }
}

