<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    public function sync(Request $request, string $chipId)
    {
        $device = Device::where('chip_id', $chipId)->firstOrFail();

        $millis = $request->input('millis');
        if ($millis) {
            $uptime = now()->subMilliseconds($millis)->diffForHumans();
            Log::info(json_encode([
                'device' => $device->id,
                'uptime' => $uptime,
                ...$request->all(),
            ]));
        }

        $device->last_sync = now();
        $device->save();

        $deviceCommandKey = 'device-command-' . $device->id;

        $command = cache()->get($deviceCommandKey);

        if (! $command) {
            return response()->noContent();
        }

        cache()->forget($deviceCommandKey);
        return response('pulse', 200, ['Content-Type' => 'text/plain']);
    }

    public function firmware(Request $request)
    {
        $chipId = $request->input('chip_id');
        $device = Device::where('chip_id', $chipId)->firstOrFail();

        $firmwareVersion = $request->input('version');

        $firmwareParts = explode(' ', $firmwareVersion);
        $firmwareDate = $firmwareParts[0];
        $firmwareNumber = $firmwareParts[1];

        if (! $firmwareDate || ! $firmwareNumber) {
            return response()->json(['error' => 'Invalid firmware version'], 400);
        }

        $lastFirmwareVersion = cache()->get('last-firmware-version');
        $lastFirmwareDate = $lastFirmwareVersion ? explode(' ', $lastFirmwareVersion)[0] : null;
        $lastFirmwareNumber = $lastFirmwareVersion ? explode(' ', $lastFirmwareVersion)[1] : 0;

        if (! $lastFirmwareVersion) {
            return response()->noContent();
        }

        if ($firmwareDate === $lastFirmwareDate && $firmwareNumber === $lastFirmwareNumber) {
            return response()->noContent();
        }

        $lastFirmwareUrl = cache()->get('last-firmware-url');

        if ($lastFirmwareUrl) {
            return response()->redirectTo($lastFirmwareUrl);
        }

        return response()->noContent();
    }
}
