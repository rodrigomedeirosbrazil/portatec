<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Events\PlaceDeviceCommandAckEvent;
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
}
