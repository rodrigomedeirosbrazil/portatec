<?php

declare(strict_types=1);

namespace App\Services\Device;

use App\Models\Device;
use App\Models\PlaceDeviceFunction;

/**
 * Porte 1:1 de `App\Livewire\Devices\Edit::syncPlaceDeviceFunctions()`.
 *
 * Mantém a tabela pivô `place_device_functions` coerente com os locais e as
 * funções atuais do dispositivo: remove os vínculos fora dos locais
 * selecionados e cria (firstOrCreate) os que faltam para cada par
 * local x função.
 */
class DevicePlaceFunctionSyncService
{
    /**
     * @param  array<int, int>  $placeIds
     */
    public function sync(Device $device, array $placeIds): void
    {
        $device->load('deviceFunctions');

        $functionIds = $device->deviceFunctions->pluck('id')->all();

        PlaceDeviceFunction::query()
            ->whereIn('device_function_id', $functionIds)
            ->whereNotIn('place_id', $placeIds)
            ->delete();

        foreach ($placeIds as $placeId) {
            foreach ($functionIds as $deviceFunctionId) {
                PlaceDeviceFunction::firstOrCreate([
                    'place_id' => $placeId,
                    'device_function_id' => $deviceFunctionId,
                ]);
            }
        }
    }
}
