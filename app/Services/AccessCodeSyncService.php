<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeviceBrandEnum;
use App\Models\AccessCode;
use App\Models\AccessCodeDeviceSync;
use App\Models\Device;
use App\Models\Place;
use App\Services\Device\DeviceCommandService;
use App\Services\Tuya\TuyaIntegrationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccessCodeSyncService
{
    public function __construct(
        private DeviceCommandService $deviceCommandService,
        private TuyaIntegrationService $tuyaIntegrationService,
    ) {}

    /**
     * Sincroniza todos os AccessCodes válidos do Place para um dispositivo
     */
    public function syncAccessCodesToDevice(Device $device): void
    {
        if (! $device->supportsPlaceAccessCodes()) {
            return;
        }

        $places = $device->places()->get();

        if ($places->isEmpty() && $device->place_id !== null) {
            $places = Place::query()->whereKey($device->place_id)->get();
        }

        if ($places->isEmpty()) {
            return;
        }

        $validAccessCodes = $places
            ->flatMap(fn (Place $place) => $this->getValidAccessCodesForPlace($place))
            ->unique('id')
            ->values();

        try {
            if ($device->brand === DeviceBrandEnum::Portatec) {
                Log::info('[Tuya sync] Enviando access codes via MQTT (dispositivo Portatec)', [
                    'device_id' => $device->id,
                    'place_id' => $device->place_id,
                    'access_codes_count' => $validAccessCodes->count(),
                ]);
                $this->deviceCommandService->syncAccessCodes($device, $validAccessCodes);

                return;
            }

            if (! $device->isTuyaLock()) {
                return;
            }

            $existingSyncs = $device->accessCodeDeviceSyncs()
                ->where('provider', 'tuya')
                ->get()
                ->keyBy('access_code_id');

            foreach ($validAccessCodes as $accessCode) {
                $this->syncTuyaAccessCodeToDevice($accessCode, $device);
            }

            $validAccessCodeIds = $validAccessCodes->pluck('id')->all();
            $existingSyncs
                ->reject(fn (AccessCodeDeviceSync $sync): bool => in_array($sync->access_code_id, $validAccessCodeIds, true))
                ->each(function (AccessCodeDeviceSync $sync) use ($device): void {
                    $this->deleteTuyaAccessCodeFromDevice($sync->accessCode, $device);
                });
        } catch (Throwable $exception) {
            Log::error('Falha ao sincronizar access codes.', [
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
        $devices = $this->devicesForPlaceAccessCode($accessCode);

        Log::info('[Tuya sync] syncNewAccessCode chamado', [
            'access_code_id' => $accessCode->id,
            'place_id' => $accessCode->place_id,
            'pin_length' => strlen($accessCode->pin),
            'start' => $accessCode->start?->toIso8601String(),
            'end' => $accessCode->end?->toIso8601String(),
            'devices_count' => $devices->count(),
            'device_ids' => $devices->pluck('id')->all(),
        ]);

        foreach ($devices as $device) {
            Log::debug('[Tuya sync] Dispositivo candidato', [
                'device_id' => $device->id,
                'name' => $device->name,
                'brand' => $device->brand?->value,
                'place_id' => $device->place_id,
                'supportsPlaceAccessCodes' => $device->supportsPlaceAccessCodes(),
                'isTuyaLock' => $device->isTuyaLock(),
                'tuya_category' => $device->tuya_category ?? null,
            ]);
            $this->syncSingleAccessCode($accessCode, $device);
        }
    }

    /**
     * Atualiza AccessCode nos dispositivos
     */
    public function syncUpdatedAccessCode(AccessCode $accessCode): void
    {
        $devices = $this->devicesForPlaceAccessCode($accessCode);

        foreach ($devices as $device) {
            $this->syncSingleAccessCode($accessCode, $device);
        }
    }

    /**
     * Remove AccessCode dos dispositivos
     */
    public function syncDeletedAccessCode(AccessCode $accessCode): void
    {
        $devices = $this->devicesForPlaceAccessCode($accessCode);

        foreach ($devices as $device) {
            if ($device->brand->value === 'portatec') {
                $this->syncAccessCodesToDevice($device);

                continue;
            }

            if ($device->isTuyaLock()) {
                $this->deleteTuyaAccessCodeFromDevice($accessCode, $device);
            }
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

    private function devicesForPlaceAccessCode(AccessCode $accessCode): Collection
    {
        $raw = Device::query()
            ->where('place_id', $accessCode->place_id)
            ->orWhereHas('places', fn ($query) => $query->where('places.id', $accessCode->place_id))
            ->get();

        Log::info('[Tuya sync] Dispositivos do place (antes do filtro supportsPlaceAccessCodes)', [
            'place_id' => $accessCode->place_id,
            'raw_count' => $raw->count(),
            'devices' => $raw->map(fn (Device $d): array => [
                'id' => $d->id,
                'name' => $d->name,
                'brand' => $d->brand?->value,
                'place_id' => $d->place_id,
                'tuya_category' => $d->tuya_category,
                'supportsPlaceAccessCodes' => $d->supportsPlaceAccessCodes(),
                'isTuyaLock' => $d->isTuyaLock(),
            ])->values()->all(),
        ]);

        return $raw
            ->filter(fn (Device $device): bool => $device->supportsPlaceAccessCodes())
            ->values();
    }

    private function syncSingleAccessCode(AccessCode $accessCode, Device $device): void
    {
        if ($device->brand === DeviceBrandEnum::Portatec) {
            Log::info('[Tuya sync] Dispositivo Portatec: sincronizando access codes via MQTT', ['device_id' => $device->id]);
            $this->syncAccessCodesToDevice($device);

            return;
        }

        if (! $device->isTuyaLock()) {
            Log::debug('[Tuya sync] Dispositivo ignorado (não é fechadura Tuya)', [
                'device_id' => $device->id,
                'brand' => $device->brand?->value,
                'tuya_category' => $device->tuya_category ?? null,
            ]);

            return;
        }

        if ($accessCode->isExpired()) {
            Log::info('[Tuya sync] Access code expirado, removendo do dispositivo', [
                'access_code_id' => $accessCode->id,
                'device_id' => $device->id,
            ]);
            $this->deleteTuyaAccessCodeFromDevice($accessCode, $device);

            return;
        }

        Log::info('[Tuya sync] Enviando PIN para fechadura Tuya', [
            'access_code_id' => $accessCode->id,
            'device_id' => $device->id,
            'external_device_id' => $device->external_device_id,
        ]);
        $this->syncTuyaAccessCodeToDevice($accessCode, $device);
    }

    private function syncTuyaAccessCodeToDevice(AccessCode $accessCode, Device $device): void
    {
        $sync = AccessCodeDeviceSync::query()->firstOrNew([
            'access_code_id' => $accessCode->id,
            'device_id' => $device->id,
        ]);

        $payloadChanged = $sync->exists
            && (
                $sync->synced_pin !== $accessCode->pin
                || ! $this->sameTimestamp($sync->synced_start, $accessCode->start)
                || ! $this->sameTimestamp($sync->synced_end, $accessCode->end)
            );

        if ($payloadChanged && $sync->external_reference) {
            $this->deleteTuyaAccessCodeFromDevice($accessCode, $device, false);
            $sync = AccessCodeDeviceSync::query()->firstOrNew([
                'access_code_id' => $accessCode->id,
                'device_id' => $device->id,
            ]);
        }

        if ($sync->exists && ! $payloadChanged && $sync->status === 'synced') {
            return;
        }

        try {
            $invalidTime = $accessCode->end !== null
                ? $accessCode->end->timestamp
                : now()->addDay()->timestamp;
            $externalReference = $this->tuyaIntegrationService->createTemporaryPasswordViaDP(
                device: $device,
                pin: $accessCode->pin,
                effectiveTime: $accessCode->start->timestamp,
                invalidTime: $invalidTime,
            );

            Log::info('[Tuya sync] PIN adicionado na fechadura com sucesso', [
                'access_code_id' => $accessCode->id,
                'device_id' => $device->id,
                'external_reference' => $externalReference,
            ]);

            $sync->fill([
                'provider' => 'tuya',
                'external_reference' => $externalReference,
                'synced_start' => $accessCode->start,
                'synced_end' => $accessCode->end,
                'synced_pin' => $accessCode->pin,
                'last_synced_at' => now(),
                'status' => 'synced',
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $sync->fill([
                'provider' => 'tuya',
                'synced_start' => $accessCode->start,
                'synced_end' => $accessCode->end,
                'synced_pin' => $accessCode->pin,
                'last_synced_at' => now(),
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ])->save();

            Log::error('[Tuya sync] Falha ao sincronizar access code com fechadura Tuya', [
                'access_code_id' => $accessCode->id,
                'device_id' => $device->id,
                'external_device_id' => $device->external_device_id,
                'error' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);
        }
    }

    private function deleteTuyaAccessCodeFromDevice(AccessCode $accessCode, Device $device, bool $markDeleted = true): void
    {
        $sync = AccessCodeDeviceSync::query()
            ->where('access_code_id', $accessCode->id)
            ->where('device_id', $device->id)
            ->first();

        if (! $sync instanceof AccessCodeDeviceSync) {
            return;
        }

        if ($sync->external_reference) {
            try {
                $this->tuyaIntegrationService->deleteTemporaryPassword($device, $sync->external_reference);
            } catch (Throwable $exception) {
                Log::warning('Falha ao remover access code de fechadura Tuya.', [
                    'access_code_id' => $accessCode->id,
                    'device_id' => $device->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if (! $markDeleted) {
            $sync->delete();

            return;
        }

        $sync->fill([
            'status' => 'deleted',
            'last_synced_at' => now(),
            'error_message' => null,
            'external_reference' => null,
        ])->save();
    }

    private function buildRemoteAccessCodeName(AccessCode $accessCode): string
    {
        return str($accessCode->display_name)
            ->limit(50, '')
            ->append(" #{$accessCode->id}")
            ->toString();
    }

    private function sameTimestamp(?CarbonInterface $first, ?CarbonInterface $second): bool
    {
        if ($first === null || $second === null) {
            return $first === $second;
        }

        return $first->equalTo($second);
    }
}
