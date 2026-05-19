<?php

namespace Tests\Unit;

use App\Models\Place;
use App\Services\ItineraryGenerator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ItineraryGenerationTest extends TestCase
{
    public function test_generate_returns_empty_result_when_no_places(): void
    {
        $generator = new ItineraryGenerator();
        $result = $generator->generate(new Collection(), 120);

        $this->assertSame(0, $result['totalDurationMin']);
        $this->assertSame(0, $result['totalDistanceKm']);
        $this->assertSame(0, $result['totalBudgetEur']);
        $this->assertSame([], $result['steps']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_generate_respects_free_only_and_budget(): void
    {
        $generator = new ItineraryGenerator();

        $freePlace = new Place();
        $freePlace->forceFill([
            'id' => 1,
            'title' => 'Free Place',
            'lat' => 48.8566,
            'lng' => 2.3522,
            'is_free' => true,
            'price_level' => 0,
            'visit_duration_min' => 30,
        ]);

        $paidPlace = new Place();
        $paidPlace->forceFill([
            'id' => 2,
            'title' => 'Paid Place',
            'lat' => 48.8570,
            'lng' => 2.3530,
            'is_free' => false,
            'price_level' => 2,
            'visit_duration_min' => 30,
        ]);

        $result = $generator->generate(new Collection([$freePlace, $paidPlace]), 120, 48.8566, 2.3522, [
            'free_only' => true,
            'budget_eur' => 10.0,
        ]);

        $this->assertCount(1, $result['steps']);
        $this->assertSame(0.0, $result['totalBudgetEur']);
        $this->assertSame('Free Place', $result['steps'][0]['title']);
    }

    public function test_generate_estimates_cost_from_price_level(): void
    {
        $generator = new ItineraryGenerator();

        $place = new Place();
        $place->forceFill([
            'id' => 3,
            'title' => 'Paid Place',
            'lat' => 48.8566,
            'lng' => 2.3522,
            'is_free' => false,
            'price_level' => 3,
            'visit_duration_min' => 30,
        ]);

        $result = $generator->generate(collect([$place]), 120, 48.8566, 2.3522);

        $this->assertSame(30.0, $result['steps'][0]['costEur']);
        $this->assertSame(30.0, $result['totalBudgetEur']);
    }
}
