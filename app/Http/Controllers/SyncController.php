<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;
use App\Services\DeviceSyncService;

class SyncController extends Controller
{
    public function __construct(
        private DeviceSyncService $deviceSyncService
    ) {}

    public function sync(Request $request, string $chipId)
    {
        $result = $this->deviceSyncService->syncDevice($chipId, $request->all());

        if (! $result['has_command']) {
            return response()->noContent();
        }

        return response($result['command'], 200, ['Content-Type' => 'text/plain']);
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
