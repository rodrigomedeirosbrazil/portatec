<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrubSensitiveTuyaStatusPayloadMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_the_ble_unlock_check_and_keeps_the_other_codes(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-1',
            'tuya_status_payload' => [
                ['code' => 'ble_unlock_check', 'value' => 'AAH//w==', 't' => 1000],
                ['47' => false, 'code' => 'lock_motor_state', 'value' => false, 't' => 1000],
            ],
        ]);

        $migration = require database_path('migrations/2026_10_07_000001_scrub_sensitive_tuya_status_payload.php');
        $migration->up();

        $this->assertSame(
            [['code' => 'lock_motor_state', 'value' => false, 't' => 1000]],
            $device->refresh()->tuya_status_payload,
        );
    }
}
