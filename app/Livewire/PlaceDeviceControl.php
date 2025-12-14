<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Events\DevicePulseEvent;
use App\Models\CommandLog;
use App\Models\Place;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class PlaceDeviceControl extends Component
{
    public Place $place;

    public array $loadingDevices = [];

    public function mount(int $placeId): void
    {
        $this->place = Place::findOrFail($placeId);

        // Verificar permissões
        if (!auth()->user()->hasRole('super_admin')
            && !$this->place->hasAccessToPlace(auth()->user())) {
            abort(403);
        }

        // Carregar relacionamentos
        $this->place->load('placeDeviceFunctions.deviceFunction.device');
    }

    public function getListeners(): array
    {
        return [
            'echo-private:Place.Device.Status.'.$this->place->id.',PlaceDeviceStatusEvent' => 'refreshDeviceFunctionStatus',
            'echo-private:Place.Device.Command.Ack.'.$this->place->id.',PlaceDeviceCommandAckEvent' => 'showDeviceCommandAck',
            'removeLoading' => 'removeLoading',
        ];
    }

    #[On('pushButton')]
    public function pushButton($deviceFunctionId): void
    {
        $this->loadingDevices[$deviceFunctionId] = true;

        try {
            $placeDeviceFunction = $this->place->placeDeviceFunctions
                ->firstWhere('device_function_id', $deviceFunctionId);

            $deviceFunction = $placeDeviceFunction->deviceFunction;

            if (!$deviceFunction) {
                Notification::make()
                    ->title(__('app.device_not_found'))
                    ->danger()
                    ->send();
                return;
            }

            broadcast(new DevicePulseEvent(
                $deviceFunction->device->external_device_id,
                ['pin' => $deviceFunction->pin]
            ));

            // Log the command
            CommandLog::create([
                'user_id' => auth()->id(),
                'place_id' => $this->place->id,
                'device_function_id' => $deviceFunction->id,
                'command_type' => 'push_button',
                'device_function_type' => $deviceFunction->type->value ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Notification::make()
                ->title(__('app.command_sent'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('app.error_sending_command', ['message' => $e->getMessage()]))
                ->danger()
                ->send();
        } finally {
            $this->dispatch('remove-loading', deviceFunctionId: $deviceFunctionId);
        }
    }

    #[On('removeLoading')]
    public function removeLoading($deviceFunctionId): void
    {
        unset($this->loadingDevices[$deviceFunctionId]);
    }

    public function refreshDeviceFunctionStatus(): void
    {
        $this->place->refresh();
        $this->place->load('placeDeviceFunctions.deviceFunction.device');
    }

    public function showDeviceCommandAck(): void
    {
        Notification::make()
            ->title(__('app.command_ack'))
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.place-device-control');
    }
}
