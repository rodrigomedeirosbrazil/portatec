<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\DeviceRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDevicePermissionRequest;
use App\Models\Device;
use App\Models\DeviceUser;
use App\Models\User;
use App\Services\Device\DeviceGrantService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DevicePermissionController extends Controller
{
    public function index(Device $device): Response
    {
        $this->authorize('managePermissions', $device);

        $device->load('deviceUsers.user');

        return Inertia::render('devices/permissions', [
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
            ],
            'admin' => $device->deviceUsers
                ->firstWhere('role', DeviceRoleEnum::Admin)
                ?->user
                ?->only(['id', 'name', 'email']),
            'grantees' => $device->deviceUsers
                ->where('role', DeviceRoleEnum::User)
                ->map(fn (DeviceUser $link): array => [
                    'id' => $link->id,
                    'user' => $link->user?->only(['id', 'name', 'email']),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(
        StoreDevicePermissionRequest $request,
        Device $device,
        DeviceGrantService $service
    ): RedirectResponse {
        $this->authorize('managePermissions', $device);

        $user = User::query()->where('email', $request->validated()['email'])->firstOrFail();

        $service->grant($device, $user);

        return redirect()
            ->route('app.devices.permissions.index', ['device' => $device->id])
            ->with('status', __('app.device_permission_granted'));
    }

    public function destroy(Device $device, int $deviceUser, DeviceGrantService $service): RedirectResponse
    {
        $this->authorize('managePermissions', $device);

        $link = DeviceUser::query()
            ->where('device_id', $device->id)
            ->where('role', DeviceRoleEnum::User)
            ->findOrFail($deviceUser);

        $service->revoke($device, $link->user);

        return redirect()
            ->route('app.devices.permissions.index', ['device' => $device->id])
            ->with('status', __('app.device_permission_revoked'));
    }
}
