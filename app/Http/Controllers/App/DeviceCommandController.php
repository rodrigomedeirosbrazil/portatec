<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendDeviceCommandRequest;
use App\Models\Device;
use App\Models\Place;
use App\Services\Device\DeviceCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DeviceCommandController extends Controller
{
    /**
     * Category C endpoint (per the migration spec): this replaces
     * `Places\Control::sendCommand()`. The control screen is realtime and
     * must not reload, so this responds JSON with the `commandId` instead of
     * redirecting, letting the front-end match the eventual broadcast ack.
     */
    public function store(SendDeviceCommandRequest $request, Place $place, DeviceCommandService $service): JsonResponse
    {
        abort_unless(
            $place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );

        $deviceId = $request->integer('device_id');
        $action = $request->string('action')->toString();
        $pin = $request->string('pin')->toString();

        $device = $place->devices()->whereKey($deviceId)->first();

        if (! $device instanceof Device) {
            return response()->json([
                'message' => __('app.device_not_found_in_place'),
            ], 404);
        }

        if (! is_numeric($pin)) {
            return response()->json([
                'message' => __('app.invalid_command_pin'),
            ], 422);
        }

        try {
            $commandId = $service->sendCommand(
                device: $device,
                action: $action,
                pin: (int) $pin,
                userId: Auth::id(),
            );

            return response()->json([
                'commandId' => $commandId,
                'message' => __('app.command_sent_to_device', ['action' => $action, 'device' => $device->name]),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => __('app.error_sending_device_command'),
            ], 500);
        }
    }
}
