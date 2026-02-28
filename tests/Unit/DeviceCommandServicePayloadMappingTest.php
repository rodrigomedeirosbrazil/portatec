<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceTypeEnum;
use App\Events\PlaceDeviceCommandAckEvent;
use App\Events\PlaceDeviceStatusEvent;
use App\Models\CommandLog;
use App\Models\Device;
use App\Models\DeviceFunction;
use App\Services\Device\DeviceCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DeviceCommandServicePayloadMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_ack_updates_command_log_acknowledged_at_when_command_id_present(): void
    {
        Event::fake([PlaceDeviceCommandAckEvent::class]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'MQTT Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = Device::create([
            'place_id' => $placeId,
            'name' => 'MQTT Device',
            'brand' => 'portatec',
            'external_device_id' => 'chip-ack-cmd',
        ]);

        $deviceFunction = DeviceFunction::create([
            'device_id' => $device->id,
            'type' => 'switch',
            'pin' => '13',
            'status' => true,
        ]);

        DB::table('place_device_functions')->insert([
            'place_id' => $placeId,
            'device_function_id' => $deviceFunction->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commandLog = CommandLog::create([
            'command_id' => 'cmd-uuid-123',
            'place_id' => $placeId,
            'device_function_id' => $deviceFunction->id,
            'command_type' => 'toggle',
            'command_payload' => json_encode(['command_id' => 'cmd-uuid-123', 'action' => 'toggle']),
            'device_function_type' => 'switch',
        ]);

        $this->assertNull($commandLog->acknowledged_at);

        $service = app(DeviceCommandService::class);
        $service->handleAck('chip-ack-cmd', [
            'pin' => '13',
            'action' => 'toggle',
            'command_id' => 'cmd-uuid-123',
        ]);

        $commandLog->refresh();
        $this->assertNotNull($commandLog->acknowledged_at);
    }

    public function test_handle_ack_dispatches_event_with_command_id(): void
    {
        Event::fake([PlaceDeviceCommandAckEvent::class]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'MQTT Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = Device::create([
            'place_id' => $placeId,
            'name' => 'MQTT Device',
            'brand' => 'portatec',
            'external_device_id' => 'chip-ack-cmd2',
        ]);

        $deviceFunction = DeviceFunction::create([
            'device_id' => $device->id,
            'type' => 'switch',
            'pin' => '13',
            'status' => true,
        ]);

        DB::table('place_device_functions')->insert([
            'place_id' => $placeId,
            'device_function_id' => $deviceFunction->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(DeviceCommandService::class);
        $service->handleAck('chip-ack-cmd2', [
            'pin' => '13',
            'action' => 'pulse',
            'command_id' => 'cmd-uuid-456',
        ]);

        Event::assertDispatched(PlaceDeviceCommandAckEvent::class, function (PlaceDeviceCommandAckEvent $event) use ($placeId, $device): bool {
            return $event->placeId === $placeId
                && $event->deviceId === $device->id
                && $event->command === 'pulse'
                && $event->pin === 13
                && $event->type === 'switch'
                && $event->commandId === 'cmd-uuid-456';
        });
    }

    public function test_handle_ack_maps_payload_and_dispatches_ack_event(): void
    {
        Event::fake([PlaceDeviceCommandAckEvent::class]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'MQTT Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = Device::create([
            'place_id' => $placeId,
            'name' => 'MQTT Device',
            'brand' => 'portatec',
            'external_device_id' => 'chip-ack-1',
        ]);

        $deviceFunction = DeviceFunction::create([
            'device_id' => $device->id,
            'type' => 'switch',
            'pin' => '13',
            'status' => true,
        ]);

        DB::table('place_device_functions')->insert([
            'place_id' => $placeId,
            'device_function_id' => $deviceFunction->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(DeviceCommandService::class);

        $service->handleAck('chip-ack-1', [
            'pin' => '13',
            'action' => 'toggle',
        ]);

        Event::assertDispatched(PlaceDeviceCommandAckEvent::class, function (PlaceDeviceCommandAckEvent $event) use ($placeId, $device): bool {
            return $event->placeId === $placeId
                && $event->deviceId === $device->id
                && $event->command === 'toggle'
                && $event->pin === 13
                && $event->type === 'switch';
        });
    }

    public function test_handle_status_updates_device_with_esp_payload(): void
    {
        Event::fake([PlaceDeviceStatusEvent::class]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'Status Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = Device::create([
            'place_id' => $placeId,
            'name' => 'Original Name',
            'brand' => 'portatec',
            'external_device_id' => 'chip-status-1',
        ]);

        $deviceFunction = DeviceFunction::create([
            'device_id' => $device->id,
            'type' => DeviceTypeEnum::Sensor,
            'pin' => '0',
            'status' => null,
        ]);

        DB::table('place_device_functions')->insert([
            'place_id' => $placeId,
            'device_function_id' => $deviceFunction->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(DeviceCommandService::class);
        $service->handleStatus('chip-status-1', [
            'device-name' => 'Portao Atualizado',
            'wifi-strength' => -65,
            'firmware-version' => '1.2.3',
            'millis' => 3600000,
            'sensor-pin' => '0',
            'sensor_value' => 1,
        ]);

        $device->refresh();
        $this->assertSame('Portao Atualizado', $device->name);
        $this->assertSame(-65, $device->wifi_strength);
        $this->assertSame('1.2.3', $device->firmware_version);
        $this->assertNotNull($device->last_sync);

        $deviceFunction->refresh();
        $this->assertSame(1, $deviceFunction->status);
    }
}
