<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceFunction;
use App\Models\Place;
use App\Services\Tuya\TuyaIntegrationService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PlaceControlController extends Controller
{
    public function show(Place $place, TuyaIntegrationService $tuyaIntegrationService): Response
    {
        $place->load(['devices.deviceFunctions', 'devices.integration']);

        abort_unless(
            $place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );

        $this->refreshTuyaSnapshots($place, $tuyaIntegrationService);

        $place->refresh()->load(['devices.deviceFunctions', 'devices.integration']);

        return Inertia::render('places/control', [
            'place' => [
                'id' => $place->id,
                'name' => $place->name,
            ],
            'devices' => $place->devices
                ->map(fn (Device $device): array => $this->mapDevice($device))
                ->all(),
            'initialFunctionStatus' => $this->initialFunctionStatus($place),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDevice(Device $device): array
    {
        $statusFunction = $device->getStatusFunction();

        $controllableFunctions = $device->deviceFunctions
            ->filter(fn (DeviceFunction $function): bool => in_array($function->type?->value, ['button', 'switch'], true))
            ->values();

        return [
            'id' => $device->id,
            'name' => $device->name,
            'is_available' => $device->isAvailable(),
            'is_tuya_lock' => $device->isTuyaLock(),
            'controllable_functions' => $controllableFunctions
                ->map(fn (DeviceFunction $function): array => [
                    'pin' => $function->pin,
                    'type' => $function->type->value,
                    'type_label' => $function->type->label(),
                ])
                ->values()
                ->all(),
            'status_function' => $statusFunction ? [
                'pin' => $statusFunction->pin,
                'status' => $statusFunction->status,
            ] : null,
        ];
    }

    /**
     * Mirrors `$initialFunctionStatusPlace` computed in `control.blade.php`:
     * `"{deviceId}-{pin}" => status`, only for devices whose status function
     * already has a known status.
     *
     * @return array<string, mixed>
     */
    private function initialFunctionStatus(Place $place): array
    {
        return $place->devices
            ->flatMap(function (Device $device): array {
                $statusFunction = $device->getStatusFunction();

                if (! $statusFunction || $statusFunction->status === null) {
                    return [];
                }

                return ["{$device->id}-{$statusFunction->pin}" => $statusFunction->status];
            })
            ->all();
    }

    private function refreshTuyaSnapshots(Place $place, TuyaIntegrationService $service): void
    {
        foreach ($place->devices as $device) {
            if ($device->brand?->value !== 'tuya') {
                continue;
            }

            try {
                $service->refreshDeviceSnapshot($device);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
