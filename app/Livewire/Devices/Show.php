<?php

declare(strict_types=1);

namespace App\Livewire\Devices;

use App\Models\CommandLog;
use App\Models\Device;
use App\Models\AccessCodeDeviceSync;
use App\Services\Tuya\TuyaIntegrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public Device $device;

    public function mount(Device $device): void
    {
        $this->device = $device->load(['places', 'place', 'deviceFunctions', 'integration']);

        abort_unless(Auth::user()->can('view', $this->device), 403);

        $this->refreshTuyaSnapshot(app(TuyaIntegrationService::class));
    }

    public function render(): View
    {
        $this->device->refresh();

        $recentCommands = CommandLog::query()
            ->whereHas('deviceFunction', fn ($query) => $query->where('device_id', $this->device->id))
            ->latest()
            ->limit(20)
            ->get();

        $recentTuyaSyncs = AccessCodeDeviceSync::query()
            ->where('device_id', $this->device->id)
            ->latest()
            ->limit(20)
            ->get();

        return view('livewire.devices.show', [
            'recentCommands' => $recentCommands,
            'recentTuyaSyncs' => $recentTuyaSyncs,
        ])->layout('layouts.client');
    }

    private function refreshTuyaSnapshot(TuyaIntegrationService $service): void
    {
        if ($this->device->brand->value !== 'tuya') {
            return;
        }

        try {
            $service->refreshDeviceSnapshot($this->device);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
