<?php

namespace App\Filament\Pages;

use App\Events\DevicePulseEvent;
use App\Models\Place;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Notifications\Notification;
use App\Models\CommandLog;
use Filament\Pages\BasePage;

class PlacePage extends BasePage
{
    public Place $place;
    public ?string $token;
    public array $loadingDevices = [];

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.place';

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
            'echo-private:Place.Device.Status.' . $this->place->id . ',PlaceDeviceStatusEvent' => 'refreshDeviceStatus',
            'echo-private:Place.Device.Command.Ack.' . $this->place->id . ',PlaceDeviceCommandAckEvent' => 'showDeviceCommandAck',
            'removeLoading' => 'removeLoading',
        ];
    }

    public function toggleDevice($deviceId): void
    {
        return;
    }

    public function pushButton($deviceId): void
    {
        $this->loadingDevices[$deviceId] = true;

        try {
            $placeDevice = $this->place->placeDevices->firstWhere('device_id', $deviceId);
            $device = $placeDevice->device;

            if (! $device) {
                Notification::make()
                    ->title('Device not found.')
                    ->danger()
                    ->send();
                return;
            }

            broadcast(new DevicePulseEvent($device->chip_id));

            // Log the command
            CommandLog::create([
                'user_id' => auth()->id(),
                'place_id' => $this->place->id,
                'device_id' => $device->id,
                'command_type' => 'push_button',
                'device_type' => $device->type->value ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Notification::make()
                ->title(__('app.command_sent'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error sending command.')
                ->danger()
                ->send();
        } finally {
            // Remove loading state after a short delay to show feedback
            $this->dispatch('remove-loading', deviceId: $deviceId);
        }
    }

    public function removeLoading($deviceId): void
    {
        unset($this->loadingDevices[$deviceId]);
    }

    public function refreshDeviceStatus(): void
    {
        // Refresh the place data to get updated device statuses
        $this->place->refresh();
        $this->place->load('placeDevices.device');
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

    public function getTitle(): string | Htmlable
    {
        return $this->place->name;
    }
}
