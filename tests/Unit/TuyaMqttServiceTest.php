<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use App\Services\Tuya\TuyaMqttService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuyaMqttServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_reported_status_of_a_device(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-1',
            'tuya_status_payload' => [],
        ]);

        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => [
                'devId' => 'dev-1',
                'status' => [['code' => 'lock_motor_state', 'value' => true]],
            ],
        ]);

        $device->refresh();
        $this->assertSame(
            [['code' => 'lock_motor_state', 'value' => true]],
            $device->tuya_status_payload,
        );
    }

    public function test_it_updates_online_state_from_biz_code(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-2',
            'tuya_online' => true,
        ]);

        (new TuyaMqttService)->handleMessage([
            'protocol' => 20,
            'data' => ['bizCode' => 'offline', 'bizData' => ['devId' => 'dev-2']],
        ]);

        $this->assertFalse($device->refresh()->tuya_online);
    }

    public function test_it_ignores_messages_for_unknown_devices(): void
    {
        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => ['devId' => 'nao-existe', 'status' => []],
        ]);

        $this->assertDatabaseCount('devices', 0);
    }
}
