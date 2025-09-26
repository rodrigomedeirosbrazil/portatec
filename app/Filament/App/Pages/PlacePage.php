<?php

namespace App\Filament\App\Pages;

use Exception;
use App\Events\DevicePulseEvent;
use App\Models\CommandLog;
use App\Models\Place;
use Filament\Notifications\Notification;
use Filament\Pages\BasePage;
use Illuminate\Contracts\Support\Htmlable;

class PlacePage extends BasePage
{
    public Place $place;

    public ?string $token;

    public array $loadingDevices = [];

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public function mount(int $id, ?string $token = null): void
    {
        if (! auth()->check()) {
            session()->put('url.intended', url()->current());
            redirect('/main/login');

            return;
        }

        $this->place = Place::findOrFail($id);
        $this->token = $token;

        if (! $this->userCanAccess() && ! $this->tokenIsValid()) {
            abort(403);
        }
    }

    public function getListeners(): array
    {
        return [
            'echo-private:Place.Device.Status.'.$this->place->id.',PlaceDeviceStatusEvent' => 'refreshDeviceFunctionStatus',
            'echo-private:Place.Device.Command.Ack.'.$this->place->id.',PlaceDeviceCommandAckEvent' => 'showDeviceCommandAck',
            'removeLoading' => 'removeLoading',
        ];
    }

    public function toggleDeviceFunction($deviceFunctionId): void {}

    public function pushButton($deviceFunctionId): void
    {
        $this->loadingDevices[$deviceFunctionId] = true;

        try {
            $placeDeviceFunction = $this->place->placeDeviceFunctions->firstWhere('device_function_id', $deviceFunctionId);
            $deviceFunction = $placeDeviceFunction->deviceFunction;

            if (! $deviceFunction) {
                Notification::make()
                    ->title('Device not found.')
                    ->danger()
                    ->send();

                return;
            }

            broadcast(new DevicePulseEvent($deviceFunction->device->chip_id, [
                'pin' => $deviceFunction->pin,
            ]));

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

        } catch (Exception $e) {
            Notification::make()
                ->title('Error sending command. '.$e->getMessage())
                ->danger()
                ->send();
        } finally {
            // Remove loading state after a short delay to show feedback
            $this->dispatch('remove-loading', deviceFunctionId: $deviceFunctionId);
        }
    }

    public function removeLoading($deviceFunctionId): void
    {
        unset($this->loadingDevices[$deviceFunctionId]);
    }

    public function refreshDeviceFunctionStatus(): void
    {
        // Refresh the place data to get updated device statuses
        $this->place->refresh();
        $this->place->load('placeDeviceFunctions.deviceFunction');
    }

    public function showDeviceCommandAck(): void
    {
        Notification::make()
            ->title(__('app.command_ack'))
            ->success()
            ->send();
    }

    public function userCanAccess(): bool
    {
        return auth()->check()
            && (auth()->user()->hasRole('super_admin')
            || $this->place->hasAccessToPlace(auth()->user()));
    }

    public function tokenIsValid(): bool
    {
        return false; // TODO: Remove this
        if (! $this->token) {
            return false;
        }

        if (auth()->check()) {
            auth()->logout();
        }

        auth()->loginUsingId($this->token);

        return $this->userCanAccess();
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->place->name;
    }
}
