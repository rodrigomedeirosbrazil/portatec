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
        $device = Device::where('chip_id', $chipId)->firstOrFail();

        $pin = data_get($data, 'pin');

        if ($pin !== null) {
            $deviceFunction = $device->deviceFunctions()->where('pin', $pin)->firstOrFail();

            if ($deviceFunction->type === DeviceTypeEnum::Sensor && isset($data['status'])) {
                $deviceFunction->status = $data['status'];
                $deviceFunction->save();
            }
        }

        $millis = data_get($data, 'millis');
        if ($millis !== null) {
            $uptime = now()->subMilliseconds($millis)->diffForHumans();
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
