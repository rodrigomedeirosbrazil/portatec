<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Device;
use App\Models\Place;
use App\Services\Device\DeviceCommandService;
use App\Services\Tuya\TuyaIntegrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Control extends Component
{
    public Place $place;

    public function mount(Place $place): void
    {
        $this->place = $place->load(['devices.deviceFunctions', 'devices.integration']);

        abort_unless(
            $this->place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );

        $this->refreshTuyaSnapshots(app(TuyaIntegrationService::class));
    }

    public function sendCommand(DeviceCommandService $service, int $deviceId, string $action, string $pin): void
    {
        abort_unless(
            $this->place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );

        $device = $this->place->devices->firstWhere('id', $deviceId);
        if (! $device instanceof Device) {
            session()->flash('status', 'Dispositivo não encontrado neste local.');
            $this->dispatch('command-failed', deviceId: $deviceId, pin: $pin);

            return;
        }

        if (! is_numeric($pin)) {
            session()->flash('status', 'PIN inválido para envio de comando.');
            $this->dispatch('command-failed', deviceId: $deviceId, pin: $pin);

            return;
        }

        try {
            $commandId = $service->sendCommand(
                device: $device,
                action: $action,
                pin: (int) $pin,
                userId: Auth::id(),
            );

            session()->flash('status', "Comando '{$action}' enviado para {$device->name}.");

            $this->dispatch('command-sent', commandId: $commandId, deviceId: $deviceId, pin: (int) $pin);
        } catch (\Throwable $exception) {
            report($exception);
            session()->flash('status', 'Erro ao enviar comando para o dispositivo.');
            $this->dispatch('command-failed', deviceId: $deviceId, pin: (int) $pin);
        }
    }

    public function render(): View
    {
        $this->place->refresh()->load(['devices.deviceFunctions', 'devices.integration']);

        return view('livewire.places.control', [
            'place' => $this->place,
        ])->layout('layouts.client');
    }

    private function refreshTuyaSnapshots(TuyaIntegrationService $service): void
    {
        foreach ($this->place->devices as $device) {
            if ($device->brand->value !== 'tuya') {
                continue;
            }

            try {
                $service->refreshDeviceSnapshot($device);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
