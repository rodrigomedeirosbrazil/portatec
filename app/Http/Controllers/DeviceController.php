<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DeviceController extends Controller
{
    public function updateFirmware(Request $request)
    {
        $chipId = $request->input('chip-id');

        Device::where('chip_id', $chipId)->firstOrFail();

        $firmwareVersion = $request->input('version');

        $firmwareParts = explode(' ', $firmwareVersion);
        $firmwareDate = data_get($firmwareParts, 0);
        $firmwareNumber = data_get($firmwareParts, 1, 0);

        if (! isset($firmwareDate)) {
            return response()->json(['error' => 'Invalid firmware version'], 400);
        }

        $lastFirmwareVersion = cache()->get('last-firmware-version');
        $lastFirmwareParts = explode(' ', $lastFirmwareVersion);
        $lastFirmwareDate = data_get($lastFirmwareParts, 0);
        $lastFirmwareNumber = data_get($lastFirmwareParts, 1, 0);

        if (! isset($lastFirmwareDate)) {
            return response()->json(['message' => 'No firmware version found'], 404);
        }

        if ($firmwareDate === $lastFirmwareDate && $firmwareNumber === $lastFirmwareNumber) {
            return response()->json(['message' => 'Firmware is up to date'], 404);
        }

        $lastFirmwareUrl = cache()->get('last-firmware-url');

        if ($lastFirmwareUrl) {
            try {
                $response = Http::timeout(30)->get($lastFirmwareUrl);

                if ($response->successful()) {
                    $filename = basename(parse_url($lastFirmwareUrl, PHP_URL_PATH)) ?: 'firmware.bin';

                    return response($response->body())
                        ->header('Content-Type', $response->header('Content-Type') ?: 'application/octet-stream')
                        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                        ->header('Content-Length', strlen($response->body()));
                }

                return response()->json(['message' => 'Failed to download firmware'], 500);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Error downloading firmware: ' . $e->getMessage()], 500);
            }
        }

        return response()->json(['message' => 'Firmware not found'], 404);
    }
}
