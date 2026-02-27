<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\ICalParserInterface;
use App\DTOs\BookingDTO;
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
}
