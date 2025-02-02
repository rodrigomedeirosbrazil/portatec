<?php

namespace App\Filament\App\Resources\PlaceResource\Pages;

use App\Enums\DeviceTypeEnum;
use App\Events\MqttMessageEvent;
use App\Filament\App\Resources\PlaceResource;
use App\Jobs\GetMqttMessageJob;
use App\Models\PlaceDevice;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Support\HtmlString;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Notifications\Notification;
use PhpMqtt\Client\Facades\MQTT;

class ViewPlace extends ViewRecord
{
    protected static string $resource = PlaceResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->askForDeviceAvailability();
        $this->askForDeviceStatus();
    }

    public function askForDeviceAvailability(): void
    {
        $this->record->placeDevices->map(
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
        $this->record->placeDevices->map(
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
            'echo-private:Place.Device.Status.' . $this->record->id . ',PlaceDeviceStatusEvent' => 'refreshDeviceStatus',
        ];
    }

    public function refreshDeviceStatus ($event): void
    {
        $this->record->placeDevices->each(function (PlaceDevice $placeDevice) use ($event) {
            if ($placeDevice->device_id !== data_get($event, 'deviceId')) {
                return;
            }

            $placeDevice->device->status = data_get($event, 'status');
        });
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Place Details')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('placeUsers.user.name')
                            ->label('Members')
                            ->formatStateUsing(
                                fn ($record): HtmlString => new HtmlString(
                                    '<ul class="list-disc list-inside">' .
                                    $record->placeUsers->map(
                                        fn ($placeUser) => '<li>' . e($placeUser->user->name) . ' (' . e($placeUser->role) . ')</li>'
                                    )->implode('') .
                                    '</ul>'
                                )
                            ),

                        RepeatableEntry::make('placeDevices')
                            ->schema([
                                TextEntry::make('device.name')
                                    ->hidden(
                                        fn ($record) => $record->device->type === DeviceTypeEnum::Sensor
                                    )
                                    ->label(
                                        fn ($record) => ! $record->device->is_available ? ' (Offline)' : ''
                                    )
                                    ->suffixAction(
                                        fn ($record) => $record->device->type === DeviceTypeEnum::Button
                                            ? Action::make('pushButton')
                                                ->button()
                                                ->disabled(
                                                    fn ($record) => !$record->device->is_available
                                                )
                                                ->icon('heroicon-m-play')
                                                ->action(function ($record) {
                                                    if (empty($record->device->command_topic)) {
                                                        return;
                                                    }
                                                    MQTT::publish($record->device->command_topic, $record->device->payload_on);

                                                    Notification::make()
                                                        ->title('Command sent.')
                                                        ->success()
                                                        ->send();
                                                })
                                            : Action::make('Switch')
                                                ->button()
                                                ->disabled(
                                                    fn ($record) => !$record->device->is_available
                                                )
                                                ->icon('heroicon-m-power')
                                                ->action(function ($record) {
                                                    if (empty($record->device->command_topic)) {
                                                        return;
                                                    }

                                                    $toggledPayload = $record->device->status === $record->device->payload_off
                                                        ? $record->device->payload_on
                                                        : $record->device->payload_off;

                                                    MQTT::publish($record->device->command_topic, $toggledPayload);

                                                    Notification::make()
                                                        ->title('Command sent.')
                                                        ->success()
                                                        ->send();
                                                })
                                    ),
                                TextEntry::make('device.name')
                                    ->hidden(
                                        fn ($record) => $record->device->type === DeviceTypeEnum::Button
                                            || $record->device->type === DeviceTypeEnum::Switch
                                    )
                                    ->label(
                                        fn ($record) => ! $record->device->is_available
                                            ? "{$record->device->name} (Offline)"
                                            : ($record->device->status === $record->device->payload_on
                                                ? "{$record->device->name} is On"
                                                : "{$record->device->name} is Off")
                                    )
                            ])
                    ])
                    ->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
