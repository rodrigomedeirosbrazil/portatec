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

        if (!$device) {
            return;
        }

        $placeDevice = $device->placeDevices()->first();

        if ($placeDevice) {
            CommandLog::create([
                'user_id' => null,
                'place_id' => $placeDevice->place_id,
                'device_id' => $device->id,
                'command_type' => 'sensor_update',
                'command_payload' => $event->changes['status'],
                'device_type' => $device->type->value ?? null,
            ]);
        }
    }
}
