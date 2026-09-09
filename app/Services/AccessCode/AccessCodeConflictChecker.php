<?php

declare(strict_types=1);

namespace App\Services\AccessCode;

use App\Models\AccessCode;
use App\Models\Device;
use App\Models\Place;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Spec §7. A unicidade do PIN não é do local, é do EQUIPAMENTO: dois locais
 * que compartilham o mesmo portão gravam os PINs deles na mesma fechadura, e
 * lá dentro dois códigos iguais e simultâneos são indistinguíveis.
 *
 * `end = null` é código permanente e vale infinito — ele ocupa aquele PIN
 * naquele dispositivo para sempre.
 */
class AccessCodeConflictChecker
{
    public function conflicts(
        int $placeId,
        string $pin,
        CarbonInterface $start,
        ?CarbonInterface $end,
        ?int $ignoreAccessCodeId = null,
    ): bool {
        $placeIds = $this->placesSharingDevicesWith($placeId);

        if ($placeIds === []) {
            return false;
        }

        return AccessCode::query()
            ->whereIn('place_id', $placeIds)
            ->where('pin', $pin)
            ->when($ignoreAccessCodeId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreAccessCodeId))
            ->where(function (Builder $query) use ($end): void {
                if ($end === null) {
                    return;
                }

                $query->where('start', '<=', $end);
            })
            ->where(function (Builder $query) use ($start): void {
                $query->whereNull('end')->orWhere('end', '>=', $start);
            })
            ->exists();
    }

    /**
     * Locais que dividem ao menos um dispositivo com o local dado — incluindo
     * ele próprio. Só dispositivos que de fato recebem PIN entram na conta.
     *
     * @return array<int, int>
     */
    private function placesSharingDevicesWith(int $placeId): array
    {
        $place = Place::query()->with('devices.places')->find($placeId);

        if ($place === null) {
            return [];
        }

        return $place->devices
            ->filter(fn (Device $device): bool => $device->supportsPlaceAccessCodes())
            ->flatMap(fn (Device $device) => $device->places->pluck('id'))
            ->push($placeId)
            ->unique()
            ->values()
            ->all();
    }
}
