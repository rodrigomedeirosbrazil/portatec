<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

class DeviceSyncService
{
    public function syncDevice(string $chipId, array $data = []): array
    {
        $device = Device::where('chip_id', $chipId)->firstOrFail();

        $millis = $data['millis'] ?? null;
        if ($millis) {
            $uptime = now()->subMilliseconds($millis)->diffForHumans();
            Log::info(json_encode([
                'device' => $device->id,
                'uptime' => $uptime,
                ...$data,
            ]));
        }

        $device->last_sync = now();
        $device->save();

        $deviceCommandKey = 'device-command-' . $device->id;
        $command = cache()->get($deviceCommandKey);

        if (! $command) {
            return [
                'has_command' => false,
                'command' => null,
            ];
        }

        cache()->forget($deviceCommandKey);

        return [
            'has_command' => true,
            'command' => $command,
        ];
    }
}
