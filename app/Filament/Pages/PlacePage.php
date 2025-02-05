<?php

namespace App\Filament\Pages;

use App\Models\Place;
use Illuminate\Contracts\Support\Htmlable;
use PhpMqtt\Client\Facades\MQTT;
use Filament\Notifications\Notification;
use App\Jobs\GetMqttMessageJob;
use App\Models\PlaceDevice;
use Filament\Pages\BasePage;

class PlacePage extends BasePage
{
    public Place $place;
    public ?string $token;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.place';

    public function mount(int $id): void
    {
        $this->place = Place::findOrFail($id);
        $this->token = null;
        $this->askForDeviceAvailability();
        $this->askForDeviceStatus();

        if (! $this->userCanAccess()) {
            abort(403);
        }
    }

    public function askForDeviceAvailability(): void
    {
        $this->place->placeDevices->map(
            fn (PlaceDevice $placeDevice) => $placeDevice->device->availability_topic
        )
            ->filter()
            ->unique()
            ->each(
                fn (string $topic) => GetMqttMessageJob::dispatch($topic)
            );
    }

    public function askForDeviceStatus(): void
    {
        $this->place->placeDevices->map(
            fn (PlaceDevice $placeDevice) => $placeDevice->device->command_topic
        )
            ->filter()
            ->unique()
            ->each(
                fn (string $topic) => MQTT::publish($topic, '')
            );
    }

    public function getListeners(): array
    {
        return [
            'echo-private:Place.Device.Status.' . $this->place->id . ',PlaceDeviceStatusEvent' => 'refreshDeviceStatus',
        ];
    }

    public function refreshDeviceStatus($event): void
    {
        $this->place->placeDevices->each(function (PlaceDevice $placeDevice) use ($event) {
            if ($placeDevice->device_id !== data_get($event, 'deviceId')) {
                return;
            }

            $placeDevice->device->status = data_get($event, 'status');
            $this->deviceStates[$placeDevice->id] = $placeDevice->device->status === $placeDevice->device->payload_on;
        });
    }

    public function toggleDevice($deviceId): void
    {
        $placeDevice = $this->place->placeDevices->firstWhere('device_id', $deviceId);
        $device = $placeDevice->device;

        if (! $device) {
            Notification::make()
                ->title('Device not found.')
                ->danger()
                ->send();

            return;
        }

        if (empty($device->command_topic)) {
            Notification::make()
                ->title('Device does not have a command topic.')
                ->danger()
                ->send();

            return;
        }

        $newState = ! $device->status;
        $payload = $newState ? $device->payload_on : $device->payload_off;

        MQTT::publish($device->command_topic, $payload);

        Notification::make()
            ->title('Command sent.')
            ->success()
            ->send();
    }

    public function pushButton($deviceId): void
    {
        $placeDevice = $this->place->placeDevices->firstWhere('device_id', $deviceId);
        $device = $placeDevice->device;
        if (! $device) {
            Notification::make()
                ->title('Device not found.')
                ->danger()
                ->send();

            return;
        }

        if (empty($device->command_topic)) {
            Notification::make()
                ->title('Device does not have a command topic.')
                ->danger()
                ->send();

            return;
        }

        MQTT::publish($device->command_topic, $device->payload_on);

        Notification::make()
            ->title('Command sent.')
            ->success()
            ->send();
    }

    public function userCanAccess(): bool
    {
        return auth()->check()
            && (auth()->user()->hasRole('super_admin')
            || $this->place->hasAccessToPlace(auth()->user()));
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
