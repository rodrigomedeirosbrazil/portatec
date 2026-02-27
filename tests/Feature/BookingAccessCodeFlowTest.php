<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingAccessCodeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_creating_booking_automatically_creates_access_code(): void
    {
        $placeId = DB::table('places')->insertGetId([
            'name' => 'Place A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking = Booking::create([
            'place_id' => $placeId,
            'guest_name' => 'Guest Flow',
            'check_in' => now()->addDay(),
            'check_out' => now()->addDays(2),
            'source' => 'manual',
        ]);

        $this->assertDatabaseHas('access_codes', [
            'place_id' => $placeId,
            'booking_id' => $booking->id,
        ]);

        $createdAccessCode = $booking->accessCode;
        $this->assertNotNull($createdAccessCode);
        $this->assertSame(6, strlen($createdAccessCode->pin));
        $this->assertSame('Guest Flow', $createdAccessCode->display_name);
    }
}
