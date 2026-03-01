<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeviceTypeEnum;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class DeviceService
{
    public function updateStatus(string $chipId, array $data = []): void
    {
        $device = Device::where('external_device_id', $chipId)->first();
        if (! $device) {
            return;
        }

        $pin = data_get($data, 'pin') ?? data_get($data, 'sensor-pin');

        if ($pin !== null) {
            $deviceFunction = $device->deviceFunctions()->where('pin', (string) $pin)->first();

            if ($deviceFunction && $deviceFunction->type === DeviceTypeEnum::Sensor) {
                $status = data_get($data, 'status') ?? data_get($data, 'sensor_value');
                if ($status !== null) {
                    $deviceFunction->status = $status;
                    $deviceFunction->save();
                }
            }
        }

        $deviceName = data_get($data, 'device-name');
        if ($deviceName !== null) {
            $device->name = (string) $deviceName;
        }

        $wifiStrength = data_get($data, 'wifi-strength');
        if ($wifiStrength !== null) {
            $device->wifi_strength = (int) $wifiStrength;
        }

        $firmwareVersion = data_get($data, 'firmware-version');
        if ($firmwareVersion !== null) {
            $device->firmware_version = (string) $firmwareVersion;
        }

        $millis = data_get($data, 'millis');
        if ($millis !== null) {
            $uptime = now()->subMilliseconds((int) $millis)->diffForHumans();
            Log::info(json_encode([
                'device' => $device->id,
                'uptime' => $uptime,
                'pin' => $pin,
                ...$data,
            ]));
        }

        $device->last_sync = now();
        $device->save();
    }
}
