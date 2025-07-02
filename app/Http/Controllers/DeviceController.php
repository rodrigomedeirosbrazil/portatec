<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function updateFirmware(Request $request)
    {
        $chipId = $request->input('chip-id')
            ?? $request->input('deviceId'); // to support old firmware

        Device::where('chip_id', $chipId)->firstOrFail();

        $firmwareVersion = $request->input('version');

        $firmwareParts = explode(' ', $firmwareVersion);
        $firmwareDate = data_get($firmwareParts, 0);
        $firmwareNumber = data_get($firmwareParts, 1, 0);

        if (! isset($firmwareDate)) {
            return response()->json(['error' => 'Invalid firmware version'], 400);
        }

        $lastFirmwareVersion = cache()->get('last-firmware-version');
        $lastFirmwareDate = $lastFirmwareVersion ? explode(' ', $lastFirmwareVersion)[0] : null;
        $lastFirmwareNumber = $lastFirmwareVersion ? explode(' ', $lastFirmwareVersion)[1] : 0;

        if (! $lastFirmwareVersion) {
            return response()->json(['message' => 'No firmware version found'], 404);
        }

        if ($firmwareDate === $lastFirmwareDate && $firmwareNumber === $lastFirmwareNumber) {
            return response()->json(['message' => 'Firmware is up to date'], 404);
        }

        $lastFirmwareUrl = cache()->get('last-firmware-url');

        if ($lastFirmwareUrl) {
            return response()->redirectTo($lastFirmwareUrl);
        }

        return response()->json(['message' => 'Firmware not found'], 404);
    }
}
