<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use App\Models\Place;

class TuyaDeviceSyncService
{
    public function __construct(
        private TuyaClientFactory $clientFactory
    ) {}

    public function syncPlaceDevices(Place $place): int
    {
        $client = $this->clientFactory->clientForPlace($place);
        if ($client === null) {
            return 0;
        }

        $credential = $place->tuyaCredential;
        if ($credential === null) {
            return 0;
        }

        $tuyaService = new TuyaService($client);
        $result = $tuyaService->getDevices($credential->uid);
        $devices = is_array($result) ? ($result['devices'] ?? $result['result'] ?? $result) : [];
        if (! is_array($devices)) {
            return 0;
        }

        $count = 0;
        foreach ($devices as $item) {
            $externalId = $item['id'] ?? $item['device_id'] ?? null;
            $name = $item['name'] ?? 'Tuya Device';
            if ($externalId === null) {
                continue;
            }

            $device = Device::query()->updateOrCreate(
                [
                    'place_id' => $place->id,
                    'external_device_id' => $externalId,
                ],
                [
                    'name' => $name,
                    'brand' => DeviceBrandEnum::Tuya,
                    'last_sync' => now(),
                ]
            );
            if ($device->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }
}
