<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceTransferRequest;
use App\Models\Device;
use App\Models\User;
use App\Services\Device\DeviceGrantService;
use Illuminate\Http\RedirectResponse;

class DeviceTransferController extends Controller
{
    public function store(
        StoreDeviceTransferRequest $request,
        Device $device,
        DeviceGrantService $service
    ): RedirectResponse {
        $this->authorize('managePermissions', $device);

        $user = User::query()->where('email', $request->validated()['email'])->firstOrFail();

        $service->transfer($device, $user);

        return redirect()
            ->route('app.devices.permissions.index', ['device' => $device->id])
            ->with('status', __('app.device_transferred', ['name' => $user->name]));
    }
}
