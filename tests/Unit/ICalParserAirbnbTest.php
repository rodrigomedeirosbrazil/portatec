<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ICalParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ICalParserAirbnbTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_airbnb_reserved_events_only(): void
    {
        $parser = app(ICalParser::class);
        $content = file_get_contents(base_path('tests/Fixtures/airbnb/listing-1119719631343107812.ics'));

        $bookings = $parser->parse($content, 'https://www.airbnb.com/calendar.ics');

        $this->assertCount(5, $bookings);

        $booking = $bookings->first(fn ($item) => $item->guestName === 'Airbnb HM2AHARRC4');
        $this->assertNotNull($booking);
        $this->assertSame('2026-03-11 17:00:00', $booking->checkIn->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-12 14:00:00', $booking->checkOut->setTimezone('UTC')->format('Y-m-d H:i:s'));
    }

    public function test_invalid_ical_content_throws_exception(): void
    {
        $parser = app(ICalParser::class);

        $this->expectException(\RuntimeException::class);

        $parser->parse('invalid content', 'https://www.airbnb.com/calendar.ics');
    }
}
