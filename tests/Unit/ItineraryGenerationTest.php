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
}
