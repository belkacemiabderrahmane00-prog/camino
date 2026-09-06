<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Place;
use App\Services\ItineraryGenerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccessibilityGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 503)]);
    }

    private function place(int $id, string $title, ?bool $accessible, float $lat, float $lng): Place
    {
        $place = new Place();
        $place->forceFill(['id' => $id, 'title' => $title, 'lat' => $lat, 'lng' => $lng, 'is_free' => true, 'visit_duration_min' => 30, 'accessible' => $accessible]);
        $place->setRelation('category', new Category(['name' => 'Musée', 'slug' => 'musee']));

        return $place;
    }

    public function test_accessible_mode_excludes_places_with_steps_and_flags_the_result(): void
    {
        $ok = $this->place(1, 'Accessible', true, 48.8566, 2.3522);
        $bad = $this->place(2, 'Escaliers', false, 48.8568, 2.3524);
        $unknown = $this->place(3, 'Inconnu', null, 48.8570, 2.3526);

        $result = app(ItineraryGenerator::class)->generate(collect([$bad, $unknown, $ok]), [
            'time_budget_min' => 180,
            'start' => ['lat' => 48.8566, 'lng' => 2.3522],
            'use_weather' => false,
            'accessible' => true,
        ]);

        $titles = array_column($result['steps'], 'title');
        $this->assertNotContains('Escaliers', $titles);
        $this->assertSame('Accessible', $titles[0]);
        $this->assertTrue($result['accessible']);
        $this->assertTrue($result['steps'][0]['accessible']);
        $this->assertStringContainsString('accessibilité', implode(' ', $result['warnings']));
    }
}
