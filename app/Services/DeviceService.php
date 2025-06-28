<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Device;
use App\Models\PlaceDevice;
use App\Enums\DeviceTypeEnum;
use Illuminate\Support\Facades\Log;

class DeviceService
{
    public function updateStatus(string $chipId, array $data = []): void
    {
        $device = Device::where('chip_id', $chipId)->firstOrFail();

        $gpio = $data['gpio'] ?? null;

        if ($gpio !== null) {
            $placeDevice = $device->placeDevices()->where('gpio', $gpio)->firstOrFail();
            // A lógica específica para o tipo de componente (sensor, pulso, etc.)
            // e a atualização do seu estado (status) viria aqui.
            // Por exemplo, se $data contém 'value' para um sensor, atualize $placeDevice->value.
        }

        $millis = $data['millis'] ?? null;
        if ($millis) {
            $uptime = now()->subMilliseconds($millis)->diffForHumans();
            Log::info(json_encode([
                'device' => $device->id,
                'uptime' => $uptime,
                'gpio' => $gpio, // Adiciona gpio ao log
                ...$data,
            ]));
        }

        $device->last_sync = now();
        $device->save();
    }
}
