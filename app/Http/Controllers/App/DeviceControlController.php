<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceFunction;
use App\Services\Tuya\TuyaIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DeviceControlController extends Controller
{
    /**
     * Ported 1:1 from `App\Livewire\Devices\Control::mount()` + `render()`
     * and the `$placeId` computation in `devices/control.blade.php`.
     */
    public function show(Request $request, Device $device, TuyaIntegrationService $tuyaIntegrationService): Response
    {
        $device->load(['places', 'deviceFunctions', 'integration']);

        abort_unless(Auth::user()?->can('view', $device), 403);

        $this->refreshTuyaSnapshot($device, $tuyaIntegrationService);

        $device->refresh()->load(['places', 'deviceFunctions', 'integration']);

        return Inertia::render('devices/control', [
            'device' => $this->mapDevice($device),
            'placeId' => $this->resolvePlaceId($device),
            'initialFunctionStatus' => $this->initialFunctionStatus($device),
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
     * Mirrors the `$placeId` computed in `control.blade.php`: first the
     * device's loaded `places`, then the legacy `place_id` column, then the
     * place of its first `PlaceDeviceFunction`, else `0` (no realtime
     * channel to subscribe to).
     */
    private function resolvePlaceId(Device $device): int
    {
        $placeId = $device->places->first()?->id
            ?? $device->place_id
            ?? $device->placeDeviceFunctions()->value('place_id');

        return (int) ($placeId ?? 0);
    }

    /**
     * Mirrors `$initialFunctionStatus` computed in `control.blade.php`:
     * `"{deviceId}-{pin}" => status`, only when the status function already
     * has a known status.
     *
     * @return array<string, mixed>
     */
    private function initialFunctionStatus(Device $device): array
    {
        $statusFunction = $device->getStatusFunction();

        if (! $statusFunction || $statusFunction->status === null) {
            return [];
        }

        return ["{$device->id}-{$statusFunction->pin}" => $statusFunction->status];
    }

    private function refreshTuyaSnapshot(Device $device, TuyaIntegrationService $service): void
    {
        if ($device->brand?->value !== 'tuya') {
            return;
        }

        try {
            $service->refreshDeviceSnapshot($device);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
