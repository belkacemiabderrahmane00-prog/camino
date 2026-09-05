<?php

namespace Tests\Unit;

use App\Services\OpeningHoursParser;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class OpeningHoursParserTest extends TestCase
{
    private OpeningHoursParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new OpeningHoursParser();
    }

    public function test_structured_hours_with_closed_day_from_note(): void
    {
        $hours = $this->parser->normalize([[
            'schema:opens' => '10:00:00', 'schema:closes' => '18:00:00',
            'schema:validFrom' => '2026-01-01T00:00:00', 'schema:validThrough' => '2026-12-31T23:59:59',
            'additionalInformation' => ['fr' => ['Fermé le mardi']],
        ]]);

        $this->assertSame('structured', $hours['confidence']);
        $this->assertSame([2], $hours['closed_days']);

        $tuesday = $this->parser->windowFor($hours, Carbon::parse('2026-09-08'));
        $this->assertSame('closed', $tuesday['status']);

        $wednesday = $this->parser->windowFor($hours, Carbon::parse('2026-09-09'));
        $this->assertSame('open', $wednesday['status']);
        $this->assertSame(600, $wednesday['opens']);
        $this->assertSame(1080, $wednesday['closes']);

        // Une période « année complète » reste valable l'année suivante (flux pas toujours remis à jour).
        $this->assertSame('open', $this->parser->windowFor($hours, Carbon::parse('2027-03-03'))['status']);
    }

    public function test_hours_parsed_from_french_note(): void
    {
        $r = $this->parser->parseNote('Mercredi-lundi, 11h-18h');
        $this->assertSame([3, 4, 5, 6, 7, 1], $r['open_days']);
        $this->assertSame('11:00', $r['opens']);
        $this->assertSame('18:00', $r['closes']);

        $r = $this->parser->parseNote('Exposition ouverte le mercredi et le samedi.');
        $this->assertSame([3, 6], $r['open_days']);

        $r = $this->parser->parseNote('Fermé les lundis et mardis. De 9h30 à 19h');
        $this->assertSame([1, 2], $r['closed_days']);
        $this->assertSame('09:30', $r['opens']);

        $r = $this->parser->parseNote('Dernière entrée à 17h15. Nocturne le jeudi.');
        $this->assertNull($r['opens']);
        $this->assertSame([], $r['closed_days']);
    }

    public function test_seasonal_periods_pick_the_right_window(): void
    {
        $hours = $this->parser->normalize([
            ['schema:opens' => '10:00:00', 'schema:closes' => '18:00:00', 'schema:validFrom' => '2026-04-01T00:00:00', 'schema:validThrough' => '2026-09-30T23:59:59'],
            ['schema:opens' => '11:00:00', 'schema:closes' => '17:00:00', 'schema:validFrom' => '2026-10-01T00:00:00', 'schema:validThrough' => '2027-03-31T23:59:59'],
        ]);

        $this->assertSame(1080, $this->parser->windowFor($hours, Carbon::parse('2026-09-08'))['closes']);
        $this->assertSame(1020, $this->parser->windowFor($hours, Carbon::parse('2026-11-08'))['closes']);
    }

    public function test_validity_period_alone_is_unknown_not_closed(): void
    {
        $hours = $this->parser->normalize([['schema:validFrom' => '2026-05-01T00:00:00', 'schema:validThrough' => '2026-08-31T23:59:59']]);

        $this->assertSame('unknown', $this->parser->windowFor($hours, Carbon::parse('2026-09-08'))['status']);
        $this->assertSame('unknown', $this->parser->windowFor(null, Carbon::parse('2026-09-08'))['status']);
    }
}
