<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AccessCode;
use App\Models\Device;
use App\Models\Place;
use App\Services\Device\DeviceCommandService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccessCodeSyncService
{
    public function __construct(
        private DeviceCommandService $deviceCommandService
    ) {}

    /**
     * Sincroniza todos os AccessCodes válidos do Place para um dispositivo
     */
    public function syncAccessCodesToDevice(Device $device): void
    {
        if (!$device->place_id) {
            return;
        }

        $place = $device->place;
        $validAccessCodes = $this->getValidAccessCodesForPlace($place);

        try {
            $this->deviceCommandService->syncAccessCodes($device, $validAccessCodes);
        } catch (Throwable $exception) {
            Log::error('Falha ao sincronizar access codes via MQTT.', [
                'device_id' => $device->id,
                'external_device_id' => $device->external_device_id,
                'place_id' => $device->place_id,
                'access_codes_count' => $validAccessCodes->count(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Envia novo AccessCode para todos os dispositivos do Place
     */
    public function syncNewAccessCode(AccessCode $accessCode): void
    {
        $devices = Device::where('place_id', $accessCode->place_id)->get();

        foreach ($devices as $device) {
            $this->syncAccessCodesToDevice($device);
        }
    }

    /**
     * Atualiza AccessCode nos dispositivos
     */
    public function syncUpdatedAccessCode(AccessCode $accessCode): void
    {
        $devices = Device::where('place_id', $accessCode->place_id)->get();

        foreach ($devices as $device) {
            $this->syncAccessCodesToDevice($device);
        }
    }

    /**
     * Remove AccessCode dos dispositivos
     */
    public function syncDeletedAccessCode(AccessCode $accessCode): void
    {
        $devices = Device::where('place_id', $accessCode->place_id)->get();

        foreach ($devices as $device) {
            $this->syncAccessCodesToDevice($device);
        }
    }

    /**
     * Retorna AccessCodes válidos (não expirados) para um Place
     */
    public function getValidAccessCodesForPlace(Place $place): Collection
    {
        return $place->accessCodes()
            ->where('start', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end')
                    ->orWhere('end', '>=', now());
            })
            ->get();
    }
}
