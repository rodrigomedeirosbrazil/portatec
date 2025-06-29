<?php

namespace App\Listeners;

use App\Events\DeviceUpdatedEvent;
use App\Models\CommandLog;
use App\Models\Device;

class DeviceStatusChangeListener
{
    public function handle(DeviceUpdatedEvent $event): void
    {
        if (! isset($event->changes['status'])) {
            return;
        }

        $device = Device::find($event->deviceId);

        if (! $device) {
            return;
        }

        $placeDeviceFunction = $device->placeDeviceFunctions()->first();

        if ($placeDeviceFunction) {
            CommandLog::create([
                'user_id' => null,
                'place_id' => $placeDeviceFunction->place_id,
                'device_function_id' => $placeDeviceFunction->deviceFunction->id,
                'device_function_type' => $placeDeviceFunction->deviceFunction->type->value ?? null,
                'command_type' => 'sensor_update',
                'command_payload' => $event->changes['status'],
                'device_type' => $device->type->value ?? null,
            ]);
        }
    }
}
