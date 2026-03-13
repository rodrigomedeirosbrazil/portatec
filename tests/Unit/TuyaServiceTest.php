<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tuya\Client as TuyaClient;
use App\Services\Tuya\TuyaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tuya.client_id' => 'test_id',
            'tuya.client_secret' => 'test_secret',
            'tuya.base_url' => 'https://openapi.tuyaus.com',
        ]);
    }

    public function test_send_device_commands_returns_true_on_success(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $client = TuyaClient::fromConfig('fake_access_token');
        $service = new TuyaService($client);

        $this->assertTrue($service->sendDeviceCommands('device_1', [
            ['code' => 'switch_1', 'value' => true],
        ]));
    }

    public function test_send_device_commands_returns_false_on_failure(): void
    {
        Http::fake(['*' => Http::response(['success' => false], 200)]);

        $client = TuyaClient::fromConfig('fake_access_token');
        $service = new TuyaService($client);

        $this->assertFalse($service->sendDeviceCommands('device_1', [
            ['code' => 'switch_1', 'value' => false],
        ]));
    }

    public function test_send_switch_calls_send_device_commands(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $client = TuyaClient::fromConfig('fake_access_token');
        $service = new TuyaService($client);

        $this->assertTrue($service->sendSwitch('device_1', true));
        $this->assertTrue($service->sendSwitch('device_1', false));
    }
}
