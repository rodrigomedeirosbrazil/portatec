<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\DeviceAccessCodeSyncEvent;
use App\Models\AccessCode;
use App\Models\Device;
use App\Models\Place;
use Illuminate\Support\Collection;

class AccessCodeSyncService
{
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

        broadcast(new DeviceAccessCodeSyncEvent(
            device: $device,
            action: 'sync',
            accessCodes: $validAccessCodes
        ));
    }

    /**
     * Envia novo AccessCode para todos os dispositivos do Place
     */
    public function syncNewAccessCode(AccessCode $accessCode): void
    {
        $devices = Device::where('place_id', $accessCode->place_id)->get();

        foreach ($devices as $device) {
            // Se o AccessCode é válido, envia como create
            // Caso contrário, não precisa sincronizar
            if ($accessCode->isValid()) {
                broadcast(new DeviceAccessCodeSyncEvent(
                    device: $device,
                    action: 'create',
                    accessCode: $accessCode
                ));
            }
        }
    }

    /**
     * Atualiza AccessCode nos dispositivos
     */
    public function syncUpdatedAccessCode(AccessCode $accessCode): void
    {
        $devices = Device::where('place_id', $accessCode->place_id)->get();

        foreach ($devices as $device) {
            // Se o AccessCode ainda é válido, envia como update
            // Caso contrário, envia como delete para remover dos dispositivos
            if ($accessCode->isValid()) {
                broadcast(new DeviceAccessCodeSyncEvent(
                    device: $device,
                    action: 'update',
                    accessCode: $accessCode
                ));
            } else {
                // AccessCode expirou, remover dos dispositivos
                broadcast(new DeviceAccessCodeSyncEvent(
                    device: $device,
                    action: 'delete',
                    accessCode: $accessCode
                ));
            }
        }
    }

    /**
     * Remove AccessCode dos dispositivos
     */
    public function syncDeletedAccessCode(AccessCode $accessCode): void
    {
        $devices = Device::where('place_id', $accessCode->place_id)->get();

        foreach ($devices as $device) {
            broadcast(new DeviceAccessCodeSyncEvent(
                device: $device,
                action: 'delete',
                accessCode: $accessCode
            ));
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
