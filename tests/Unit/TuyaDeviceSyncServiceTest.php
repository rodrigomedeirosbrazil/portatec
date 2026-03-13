<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Place;
use App\Models\TuyaCredential;
use App\Services\Tuya\TuyaClientFactory;
use App\Services\Tuya\TuyaDeviceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaDeviceSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tuya.client_id' => 'id',
            'tuya.client_secret' => 'secret',
            'tuya.base_url' => 'https://openapi.tuyaus.com',
        ]);
    }

    public function test_sync_place_devices_creates_devices_from_tuya_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    ['id' => 'tuya_dev_1', 'name' => 'Lock 1'],
                    ['id' => 'tuya_dev_2', 'name' => 'Switch 1'],
                ],
            ], 200),
        ]);

        $place = Place::query()->create(['name' => 'Place']);
        TuyaCredential::query()->create([
            'place_id' => $place->id,
            'access_token' => 'at',
            'refresh_token' => 'rt',
            'expires_at' => now()->addHour(),
            'uid' => 'tuya_uid',
        ]);

        $factory = new TuyaClientFactory;
        $service = new TuyaDeviceSyncService($factory);

        $count = $service->syncPlaceDevices($place);

        $this->assertSame(2, $count);
        $this->assertDatabaseHas('devices', [
            'place_id' => $place->id,
            'external_device_id' => 'tuya_dev_1',
            'name' => 'Lock 1',
        ]);
        $this->assertDatabaseHas('devices', [
            'place_id' => $place->id,
            'external_device_id' => 'tuya_dev_2',
            'name' => 'Switch 1',
        ]);
    }

    public function test_sync_returns_zero_when_no_credential(): void
    {
        $place = Place::query()->create(['name' => 'Place']);
        $factory = new TuyaClientFactory;
        $service = new TuyaDeviceSyncService($factory);

        $this->assertSame(0, $service->syncPlaceDevices($place));
    }
}
