<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Place;
use App\Services\ItineraryGenerator;
use App\Services\TransitService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransitGenerationTest extends TestCase
{
    private function journeyResponse(): array
    {
        return ['journeys' => [[
            'duration' => 1200, 'durations' => ['walking' => 420],
            'sections' => [
                ['type' => 'street_network', 'mode' => 'walking', 'duration' => 240, 'from' => ['name' => 'Départ'], 'to' => ['name' => 'Louvre - Rivoli (Paris)'], 'geojson' => ['coordinates' => [[2.3376, 48.8606], [2.3410, 48.8600]]]],
                ['type' => 'public_transport', 'duration' => 720, 'from' => ['name' => 'Louvre - Rivoli (Paris)'], 'to' => ['name' => 'Palais de Tokyo (Paris)'], 'display_informations' => ['physical_mode' => 'Bus', 'code' => '72', 'direction' => 'Parc de Saint-Cloud (Saint-Cloud)', 'color' => 'FF1400', 'text_color' => 'FFFFFF'], 'stop_date_times' => [[], [], [], []], 'geojson' => ['coordinates' => [[2.3410, 48.8600], [2.3000, 48.8620], [2.2960, 48.8640]]]],
                ['type' => 'street_network', 'mode' => 'walking', 'duration' => 180, 'from' => ['name' => 'Palais de Tokyo (Paris)'], 'to' => ['name' => 'Arrivée'], 'geojson' => ['coordinates' => [[2.2960, 48.8640], [2.2945, 48.8584]]]],
            ],
        ]]];
    }

    public function test_transit_service_parses_a_journey_with_lines_and_instructions(): void
    {
        config(['camino.transit.api_key' => 'test-key']);
        Http::fake(['prim.iledefrance-mobilites.fr/*' => Http::response($this->journeyResponse(), 200)]);

        $j = app(TransitService::class)->journey(['lat' => 48.8606, 'lng' => 2.3376], ['lat' => 48.8584, 'lng' => 2.2945], Carbon::parse('2026-09-08 10:00'));

        $this->assertSame(20, $j['duration_min']);
        $this->assertSame(7, $j['walking_min']);
        $this->assertSame('Bus 72', $j['summary']);
        $this->assertSame('#FF1400', $j['lines'][0]['color']);
        $this->assertCount(7, $j['shape']);
        $this->assertStringContainsString('Prends Bus 72 direction Parc de Saint-Cloud', $j['maneuvers'][1]['text']);
        $this->assertStringContainsString('Descendez à Palais de Tokyo, dans 3 arrêts', $j['maneuvers'][1]['verbal']);
        Http::assertSent(fn ($req) => $req->hasHeader('apiKey', 'test-key'));
    }

    private function farPlace(): Place
    {
        $far = new Place();
        $far->forceFill(['id' => 1, 'title' => 'Palais de Tokyo', 'lat' => 48.8584, 'lng' => 2.2945, 'is_free' => true, 'visit_duration_min' => 45]);
        $far->setRelation('category', new Category(['name' => 'Musée', 'slug' => 'musee']));

        return $far;
    }

    public function test_transit_mode_falls_back_to_walking_when_not_configured(): void
    {
        config(['camino.transit.api_key' => '']);
        Http::fake(['*' => Http::response(null, 503)]);
        $result = app(ItineraryGenerator::class)->generate(collect([$this->farPlace()]), ['time_budget_min' => 240, 'mode' => 'transit', 'start' => ['lat' => 48.8606, 'lng' => 2.3376], 'use_weather' => false]);
        $this->assertSame('transit', $result['mode']);
        $this->assertFalse($result['transit_used']);
        $this->assertStringContainsString('non configurés', implode(' ', $result['warnings']));
    }

    public function test_transit_mode_replaces_long_walks(): void
    {
        $far = $this->farPlace();
        // Le tronçon de 60 min à pied devient un trajet en bus de 20 min, avec ses manœuvres.
        config(['camino.transit.api_key' => 'test-key']);
        Http::fake(['prim.iledefrance-mobilites.fr/*' => Http::response($this->journeyResponse(), 200), '*' => Http::response(null, 503)]);
        $result = app(ItineraryGenerator::class)->generate(collect([$far]), ['time_budget_min' => 240, 'mode' => 'transit', 'start' => ['lat' => 48.8606, 'lng' => 2.3376], 'use_weather' => false]);
        $this->assertTrue($result['transit_used']);
        $this->assertSame('transit', $result['steps'][0]['travel_mode']);
        $this->assertSame(20, $result['steps'][0]['travel_minutes']);
        $this->assertSame('Bus 72', $result['steps'][0]['transit']['summary']);
        $this->assertTrue($result['legs'][0]['transit']);
        $this->assertNotEmpty($result['legs'][0]['maneuvers']);
    }
}
