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
        $stop = fn (string $name, string $at) => ['stop_point' => ['name' => $name], 'arrival_date_time' => $at, 'departure_date_time' => $at];
        $best = [
            'duration' => 1200, 'durations' => ['walking' => 420], 'nb_transfers' => 0,
            'departure_date_time' => '20260908T100200', 'arrival_date_time' => '20260908T102200',
            'sections' => [
                ['type' => 'street_network', 'mode' => 'walking', 'duration' => 240, 'departure_date_time' => '20260908T100200', 'arrival_date_time' => '20260908T100600', 'from' => ['name' => 'Départ'], 'to' => ['name' => 'Louvre - Rivoli (Paris)'], 'geojson' => ['coordinates' => [[2.3376, 48.8606], [2.3410, 48.8600]]]],
                ['type' => 'waiting', 'duration' => 120, 'departure_date_time' => '20260908T100600', 'arrival_date_time' => '20260908T100800'],
                ['type' => 'public_transport', 'duration' => 720, 'departure_date_time' => '20260908T100800', 'arrival_date_time' => '20260908T102000', 'from' => ['name' => 'Louvre - Rivoli (Paris)'], 'to' => ['name' => 'Palais de Tokyo (Paris)'],
                    'display_informations' => ['physical_mode' => 'Bus', 'code' => '72', 'direction' => 'Parc de Saint-Cloud (Saint-Cloud)', 'color' => 'FF1400', 'text_color' => 'FFFFFF', 'links' => [['type' => 'disruption', 'id' => 'd1']]],
                    'stop_date_times' => [$stop('Louvre - Rivoli (Paris)', '20260908T100800'), $stop('Pont Neuf (Paris)', '20260908T101200'), $stop('Alma - Marceau (Paris)', '20260908T101600'), $stop('Palais de Tokyo (Paris)', '20260908T102000')],
                    'geojson' => ['coordinates' => [[2.3410, 48.8600], [2.3000, 48.8620], [2.2960, 48.8640]]]],
                ['type' => 'street_network', 'mode' => 'walking', 'duration' => 180, 'departure_date_time' => '20260908T102000', 'arrival_date_time' => '20260908T102200', 'from' => ['name' => 'Palais de Tokyo (Paris)'], 'to' => ['name' => 'Arrivée'], 'geojson' => ['coordinates' => [[2.2960, 48.8640], [2.2945, 48.8584]]]],
            ],
        ];
        $other = $best;
        $other['duration'] = 1500;
        $other['departure_date_time'] = '20260908T101000';
        $other['arrival_date_time'] = '20260908T103500';
        $other['sections'][2]['display_informations']['code'] = '63';
        $walkOnly = ['duration' => 3600, 'durations' => ['walking' => 3600], 'sections' => [['type' => 'street_network', 'mode' => 'walking', 'duration' => 3600, 'from' => ['name' => 'Départ'], 'to' => ['name' => 'Arrivée'], 'geojson' => ['coordinates' => [[2.3376, 48.8606], [2.2945, 48.8584]]]]]];

        return [
            'journeys' => [$best, $other, $walkOnly],
            'disruptions' => [['id' => 'd1', 'severity' => ['effect' => 'SIGNIFICANT_DELAYS', 'name' => 'perturbée'], 'messages' => [['text' => 'Bus 72 : travaux, <b>arrêt non desservi</b>']]]],
        ];
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
        Http::assertSent(fn ($req) => $req->hasHeader('apiKey', 'test-key') && $req->hasHeader('Accept-Encoding', 'gzip'));
    }

    public function test_transit_service_exposes_times_stops_alerts_and_alternatives(): void
    {
        config(['camino.transit.api_key' => 'test-key']);
        Http::fake(['prim.iledefrance-mobilites.fr/*' => Http::response($this->journeyResponse(), 200)]);

        $journeys = app(TransitService::class)->journeys(['lat' => 48.8606, 'lng' => 2.3376], ['lat' => 48.8584, 'lng' => 2.2945], Carbon::parse('2026-09-08 10:00'));
        $this->assertCount(2, $journeys, 'les trajets 100 % à pied sont écartés');
        $j = $journeys[0];

        $this->assertSame('10:02', $j['depart_at']);
        $this->assertSame('10:22', $j['arrive_at']);
        $this->assertSame(['walk', 'wait', 'pt', 'walk'], array_column($j['sections'], 'type'));
        $pt = $j['sections'][2];
        $this->assertSame('10:08', $pt['depart_at']);
        $this->assertSame('10:20', $pt['arrive_at']);
        $this->assertSame(3, $pt['stops']);
        $this->assertSame(['Pont Neuf', 'Alma - Marceau', 'Palais de Tokyo'], $pt['stop_names']);
        $this->assertSame('Parc de Saint-Cloud', $pt['direction']);
        $this->assertSame('warning', $pt['alerts'][0]['severity']);
        $this->assertSame('Bus 72 : travaux, arrêt non desservi', $pt['alerts'][0]['text']);
        $this->assertSame(2, $j['sections'][1]['minutes']);
        $this->assertGreaterThan(0, $j['sections'][0]['distance_m']);
        $this->assertSame(3, $j['maneuvers'][1]['stops']);
        $this->assertSame('10:20', $j['maneuvers'][1]['arrive_at']);

        $this->assertCount(1, $j['alternatives']);
        $this->assertSame('10:10', $j['alternatives'][0]['depart_at']);
        $this->assertSame('Bus 63', $j['alternatives'][0]['summary']);
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
        // Le tronçon de 60 min à pied devient un trajet en bus de 20 min, avec ses manœuvres et son détail horaire.
        config(['camino.transit.api_key' => 'test-key']);
        Http::fake(['prim.iledefrance-mobilites.fr/*' => Http::response($this->journeyResponse(), 200), '*' => Http::response(null, 503)]);
        $result = app(ItineraryGenerator::class)->generate(collect([$far]), ['time_budget_min' => 240, 'mode' => 'transit', 'start' => ['lat' => 48.8606, 'lng' => 2.3376], 'use_weather' => false]);
        $this->assertTrue($result['transit_used']);
        $this->assertSame('transit', $result['steps'][0]['travel_mode']);
        $this->assertSame(20, $result['steps'][0]['travel_minutes']);
        $this->assertSame('Bus 72', $result['steps'][0]['transit']['summary']);
        $this->assertSame('10:08', $result['steps'][0]['transit']['sections'][2]['depart_at']);
        $this->assertCount(1, $result['steps'][0]['transit']['alerts']);
        $this->assertCount(1, $result['steps'][0]['transit']['alternatives']);
        $this->assertTrue($result['legs'][0]['transit']);
        $this->assertSame('10:22', $result['legs'][0]['arrive_at']);
        $this->assertNotEmpty($result['legs'][0]['maneuvers']);
    }

    public function test_transit_api_returns_realtime_journeys_for_guidance(): void
    {
        config(['camino.transit.api_key' => 'test-key']);
        Http::fake(['prim.iledefrance-mobilites.fr/*' => Http::response($this->journeyResponse(), 200)]);

        $response = $this->postJson('/api/v1/transit', ['from' => ['lat' => 48.8606, 'lng' => 2.3376], 'to' => ['lat' => 48.8584, 'lng' => 2.2945]]);

        $response->assertOk()->assertJsonPath('enabled', true)->assertJsonPath('journeys.0.summary', 'Bus 72')->assertJsonPath('journeys.0.sections.2.stops', 3);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'data_freshness=realtime'));
    }

    public function test_transit_api_is_disabled_without_key(): void
    {
        config(['camino.transit.api_key' => '']);
        $this->postJson('/api/v1/transit', ['from' => ['lat' => 48.8606, 'lng' => 2.3376], 'to' => ['lat' => 48.8584, 'lng' => 2.2945]])->assertOk()->assertJsonPath('enabled', false);
    }

    public function test_transit_sheet_renders_sections_stops_and_alerts(): void
    {
        config(['camino.transit.api_key' => 'test-key']);
        Http::fake(['prim.iledefrance-mobilites.fr/*' => Http::response($this->journeyResponse(), 200)]);
        $j = app(TransitService::class)->journey(['lat' => 48.8606, 'lng' => 2.3376], ['lat' => 48.8584, 'lng' => 2.2945], Carbon::parse('2026-09-08 10:00'));

        $html = view('components.transit-sheet', ['transit' => ItineraryGenerator::transitInfo($j), 'minutes' => 20, 'open' => true, 'compact' => false, 'attributes' => new \Illuminate\View\ComponentAttributeBag()])->render();

        $this->assertStringContainsString('Bus 72', $html);
        $this->assertStringContainsString('Louvre - Rivoli', $html);
        $this->assertStringContainsString('Palais de Tokyo', $html);
        $this->assertStringContainsString('Alma - Marceau', $html);
        $this->assertStringContainsString('10:08', $html);
        $this->assertStringContainsString('3 arrêts', $html);
        $this->assertStringContainsString('arrêt non desservi', $html);
        $this->assertStringContainsString('10:10', $html, 'autre départ proposé');
    }
}
