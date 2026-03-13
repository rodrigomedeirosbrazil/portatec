<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TuyaWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_accepts_post_and_returns_success(): void
    {
        Queue::fake();

        $response = $this->postJson(route('webhooks.tuya'), [
            'dataId' => 'evt_123',
            'dataType' => 'deviceStatus',
            'data' => [
                'deviceId' => 'tuya_device_xyz',
                'status' => [['code' => 'switch_1', 'value' => true]],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_webhook_job_updates_device_last_sync_when_device_exists(): void
    {
        $place = Place::query()->create(['name' => 'Place']);
        $device = Device::query()->create([
            'place_id' => $place->id,
            'name' => 'Tuya Lock',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'tuya_device_123',
        ]);

        $this->postJson(route('webhooks.tuya'), [
            'dataId' => 'evt_1',
            'dataType' => 'deviceStatus',
            'data' => [
                'deviceId' => 'tuya_device_123',
                'status' => [],
            ],
        ]);

        $device->refresh();
        $this->assertNotNull($device->last_sync);
    }
}
