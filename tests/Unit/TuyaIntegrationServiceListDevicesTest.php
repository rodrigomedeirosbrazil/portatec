<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Integration;
use App\Services\Tuya\TuyaCustomerApiClient;
use App\Services\Tuya\TuyaIntegrationService;
use Mockery;
use Tests\TestCase;

class TuyaIntegrationServiceListDevicesTest extends TestCase
{
    public function test_the_listed_device_status_never_carries_sensitive_dps(): void
    {
        $integration = new Integration;

        $client = Mockery::mock(TuyaCustomerApiClient::class);
        $client->shouldReceive('get')
            ->with($integration, '/v1.0/m/life/users/homes')
            ->andReturn([['ownerId' => 1]]);
        $client->shouldReceive('get')
            ->with($integration, '/v1.0/m/life/ha/home/devices', ['homeId' => '1'])
            ->andReturn([[
                'id' => 'dev-1',
                'name' => 'Fechadura',
                'category' => 'ms',
                'online' => true,
                'status' => [
                    ['code' => 'lock_motor_state', 'value' => false],
                    ['code' => 'ble_unlock_check', 'value' => 'AAH//w=='],
                ],
            ]]);

        $devices = (new TuyaIntegrationService($client))->listDevices($integration);

        $this->assertCount(1, $devices);
        $this->assertSame(
            [['code' => 'lock_motor_state', 'value' => false, 't' => null]],
            $devices->first()->status,
        );
    }
}
