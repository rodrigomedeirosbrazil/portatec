<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeviceStatusEnum;
use App\Enums\DeviceTypeEnum;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class DeviceService
{
    public function updateStatus(string $chipId, array $data = []): void
    {
        $device = Device::where('chip_id', $chipId)->firstOrFail();

        $gpio = $data['gpio'] ?? null;

        if ($gpio !== null) {
            $placeDevice = $device->placeDevices()->where('gpio', $gpio)->firstOrFail();

            if ($placeDevice->type === DeviceTypeEnum::Sensor && isset($data['status'])) {
                $status = DeviceStatusEnum::tryFrom($data['status']);
                if ($status) {
                    $placeDevice->status = $status;
                    $placeDevice->save();
                }
            }
        }

        $millis = $data['millis'] ?? null;
        if ($millis) {
            $uptime = now()->subMilliseconds($millis)->diffForHumans();
            Log::info(json_encode([
                'device' => $device->id,
                'uptime' => $uptime,
                'gpio' => $gpio,
                ...$data,
            ]));
        }

        $device->last_sync = now();
        $device->save();
    }
}
