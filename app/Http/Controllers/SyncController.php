<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    public function sync(Request $request, string $chipId)
    {
        $device = Device::where('chip_id', $chipId)->first();

        if (! $device) {
            Log::error("Device {$chipId} not found");
            return response()->json(['error' => 'Device not found'], 404);
        }

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
}
