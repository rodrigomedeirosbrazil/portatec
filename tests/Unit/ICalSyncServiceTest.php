<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\ICalParserInterface;
use App\DTOs\BookingDTO;
use App\Models\Booking;
use App\Services\ICalSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ICalSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_sync_place_integration_imports_basic_ical_booking(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Sync User',
            'email' => 'sync@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $platformId = DB::table('platforms')->insertGetId([
            'name' => 'Airbnb',
            'slug' => 'airbnb',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $integrationId = DB::table('integrations')->insertGetId([
            'platform_id' => $platformId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'iCal Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $icalUrl = 'https://example.test/calendar.ics';

        DB::table('place_integration')->insert([
            'place_id' => $placeId,
            'integration_id' => $integrationId,
            'external_id' => $icalUrl,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            $icalUrl => Http::response("BEGIN:VCALENDAR\nEND:VCALENDAR", 200),
        ]);

        $parser = Mockery::mock(ICalParserInterface::class);
        $parser->shouldReceive('parse')
            ->once()
            ->andReturn(collect([
                new BookingDTO(
                    externalId: 'evt-001',
                    guestName: 'Guest iCal',
                    checkIn: Carbon::parse('2026-03-01 14:00:00'),
                    checkOut: Carbon::parse('2026-03-03 11:00:00'),
                ),
            ]));

        $this->app->instance(ICalParserInterface::class, $parser);

        $service = app(ICalSyncService::class);
        $service->syncPlaceIntegration($placeId, $integrationId);

        $this->assertDatabaseHas('bookings', [
            'place_id' => $placeId,
            'integration_id' => $integrationId,
            'external_id' => 'evt-001',
            'guest_name' => 'Guest iCal',
            'source' => 'ical',
        ]);
    }

    public function test_sync_place_integration_soft_deletes_and_recreates_when_booking_changes(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Sync User',
            'email' => 'sync2@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $platformId = DB::table('platforms')->insertGetId([
            'name' => 'Airbnb',
            'slug' => 'airbnb',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $integrationId = DB::table('integrations')->insertGetId([
            'platform_id' => $platformId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'iCal Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $icalUrl = 'https://example.test/calendar.ics';

        DB::table('place_integration')->insert([
            'place_id' => $placeId,
            'integration_id' => $integrationId,
            'external_id' => $icalUrl,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking = Booking::create([
            'place_id' => $placeId,
            'integration_id' => $integrationId,
            'external_id' => 'evt-001',
            'guest_name' => 'Guest iCal',
            'check_in' => Carbon::parse('2026-03-01 14:00:00'),
            'check_out' => Carbon::parse('2026-03-03 11:00:00'),
            'source' => 'ical',
        ]);

        Http::fake([
            $icalUrl => Http::response("BEGIN:VCALENDAR\nEND:VCALENDAR", 200),
        ]);

        $parser = Mockery::mock(ICalParserInterface::class);
        $parser->shouldReceive('parse')
            ->once()
            ->andReturn(collect([
                new BookingDTO(
                    externalId: 'evt-001',
                    guestName: 'Guest iCal',
                    checkIn: Carbon::parse('2026-03-02 14:00:00'),
                    checkOut: Carbon::parse('2026-03-04 11:00:00'),
                ),
            ]));

        $this->app->instance(ICalParserInterface::class, $parser);

        $service = app(ICalSyncService::class);
        $service->syncPlaceIntegration($placeId, $integrationId);

        $booking->refresh();
        $this->assertNotNull($booking->deleted_at);
        $this->assertDatabaseHas('bookings', [
            'place_id' => $placeId,
            'integration_id' => $integrationId,
            'external_id' => 'evt-001',
            'deleted_at' => null,
        ]);
    }

    public function test_sync_place_integration_does_not_remove_existing_bookings_on_parse_failure(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Sync User',
            'email' => 'sync3@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $platformId = DB::table('platforms')->insertGetId([
            'name' => 'Airbnb',
            'slug' => 'airbnb',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $integrationId = DB::table('integrations')->insertGetId([
            'platform_id' => $platformId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'iCal Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $icalUrl = 'https://example.test/calendar.ics';

        DB::table('place_integration')->insert([
            'place_id' => $placeId,
            'integration_id' => $integrationId,
            'external_id' => $icalUrl,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking = Booking::create([
            'place_id' => $placeId,
            'integration_id' => $integrationId,
            'external_id' => 'evt-001',
            'guest_name' => 'Guest iCal',
            'check_in' => Carbon::parse('2026-03-01 14:00:00'),
            'check_out' => Carbon::parse('2026-03-03 11:00:00'),
            'source' => 'ical',
        ]);

        Http::fake([
            $icalUrl => Http::response('INVALID', 200),
        ]);

        $parser = Mockery::mock(ICalParserInterface::class);
        $parser->shouldReceive('parse')
            ->once()
            ->andThrow(new \RuntimeException('Invalid iCal'));

        $this->app->instance(ICalParserInterface::class, $parser);

        $service = app(ICalSyncService::class);

        try {
            $service->syncPlaceIntegration($placeId, $integrationId);
        } catch (\RuntimeException) {
        }

        $booking->refresh();
        $this->assertNull($booking->deleted_at);
    }
}
