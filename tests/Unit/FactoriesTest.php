<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceBrandEnum;
use App\Models\Booking;
use App\Models\Device;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_place_factory_creates_a_place(): void
    {
        $this->assertNotNull(Place::factory()->create()->name);
    }

    public function test_device_factory_defaults_to_portatec_and_accepts_overrides(): void
    {
        $default = Device::factory()->create();
        $this->assertSame(DeviceBrandEnum::Portatec, $default->brand);

        $tuya = Device::factory()->create(['brand' => DeviceBrandEnum::Tuya, 'tuya_online' => true]);
        $this->assertTrue($tuya->isAvailable());
    }

    public function test_booking_factory_creates_a_place_and_a_coherent_window(): void
    {
        $booking = Booking::factory()->create();

        $this->assertNotNull($booking->place_id);
        $this->assertTrue($booking->check_out->greaterThan($booking->check_in));
    }
}
