<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlaceRoleEnum;
use App\Models\Device;
use App\Models\DeviceFunction;
use App\Models\Place;
use App\Models\PlaceDeviceFunction;
use App\Models\PlaceUser;
use Illuminate\Support\Facades\DB;

class PlaceCloneService
{
    /**
     * Clone a place: new place with copied devices and device functions, no external_device_id.
     * Cloner becomes Admin; optional additional members can be added.
     *
     * @param  array<int, array{user_id: int, role: string, label?: string|null}>  $additionalMembers
     */
    public function clone(
        Place $source,
        string $newName,
        int $clonerUserId,
        array $additionalMembers = []
    ): Place {
        return DB::transaction(function () use ($source, $newName, $clonerUserId, $additionalMembers): Place {
            $source->load(['devices.deviceFunctions']);

            $newPlace = Place::create(['name' => $newName]);

            PlaceUser::create([
                'place_id' => $newPlace->id,
                'user_id' => $clonerUserId,
                'role' => PlaceRoleEnum::Admin->value,
                'label' => null,
            ]);

            foreach ($additionalMembers as $member) {
                $userId = (int) $member['user_id'];
                if ($userId === $clonerUserId) {
                    continue;
                }
                $role = $member['role'] ?? PlaceRoleEnum::Host->value;
                if (! in_array($role, [PlaceRoleEnum::Admin->value, PlaceRoleEnum::Host->value], true)) {
                    $role = PlaceRoleEnum::Host->value;
                }
                PlaceUser::firstOrCreate(
                    [
                        'place_id' => $newPlace->id,
                        'user_id' => $userId,
                    ],
                    [
                        'role' => $role,
                        'label' => $member['label'] ?? null,
                    ]
                );
            }

            foreach ($source->devices as $sourceDevice) {
                $newDevice = Device::withoutEvents(function () use ($sourceDevice, $newPlace): Device {
                    return Device::create([
                        'place_id' => $newPlace->id,
                        'name' => $sourceDevice->name,
                        'brand' => $sourceDevice->brand,
                        'default_pin' => $sourceDevice->default_pin,
                        'external_device_id' => null,
                        'last_sync' => null,
                    ]);
                });

                foreach ($sourceDevice->deviceFunctions as $sourceFn) {
                    $newFn = DeviceFunction::create([
                        'device_id' => $newDevice->id,
                        'type' => $sourceFn->type,
                        'pin' => $sourceFn->pin,
                        'status' => $sourceFn->status,
                    ]);

                    PlaceDeviceFunction::create([
                        'place_id' => $newPlace->id,
                        'device_function_id' => $newFn->id,
                    ]);
                }
            }

            return $newPlace->fresh();
        });
    }
}
