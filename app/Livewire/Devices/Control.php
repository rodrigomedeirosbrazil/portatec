<?php

declare(strict_types=1);

namespace App\Livewire\Devices;

use App\Models\Device;
use App\Services\Device\DeviceCommandService;
use App\Services\Tuya\TuyaIntegrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Control extends Component
{
    public Device $device;

    public function mount(Device $device): void
    {
        $this->device = $device->load(['places', 'deviceFunctions', 'integration']);

        abort_unless(Auth::user()->can('view', $this->device), 403);

        $this->refreshTuyaSnapshot(app(TuyaIntegrationService::class));
    }

    public function sendCommand(DeviceCommandService $service, string $action, string $pin): void
    {
        abort_unless(Auth::user()->can('view', $this->device), 403);

        if (! is_numeric($pin)) {
            session()->flash('status', 'PIN inválido para envio de comando.');
            $this->dispatch('command-failed', deviceId: $this->device->id, pin: $pin);

            return;
        }

        try {
            $commandId = $service->sendCommand(
                device: $this->device,
                action: $action,
                pin: (int) $pin,
                userId: Auth::id(),
            );

            session()->flash('status', "Comando '{$action}' enviado para {$this->device->name}.");

            $this->dispatch('command-sent', commandId: $commandId, deviceId: $this->device->id, pin: (int) $pin);
        } catch (\Throwable $exception) {
            report($exception);
            session()->flash('status', 'Erro ao enviar comando para o dispositivo.');
            $this->dispatch('command-failed', deviceId: $this->device->id, pin: (int) $pin);
        }
    }

    public function render(): View
    {
        $this->device->refresh();

        $controllableFunctions = $this->device->deviceFunctions
            ->filter(fn ($function) => in_array($function->type?->value, ['button', 'switch'], true))
            ->values();

        return view('livewire.devices.control', [
            'controllableFunctions' => $controllableFunctions,
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
