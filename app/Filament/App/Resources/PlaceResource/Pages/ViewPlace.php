<?php

namespace App\Filament\App\Resources\PlaceResource\Pages;

use App\Enums\DeviceTypeEnum;
use App\Filament\App\Resources\PlaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Support\HtmlString;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use PhpMqtt\Client\Facades\MQTT;

class ViewPlace extends ViewRecord
{
    protected static string $resource = PlaceResource::class;

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
                                    ->label(fn ($record) => $record->device->type->value)
                                    ->suffixAction(
                                        fn ($record) => $record->device->type === DeviceTypeEnum::Button
                                            ? Action::make('pushButton')
                                                ->button()
                                                ->icon('heroicon-m-play')
                                                ->action(function ($record) {
                                                    if (empty($record->device->command_topic)) {
                                                        return;
                                                    }
                                                    MQTT::publish($record->device->command_topic, $record->device->payload_on);
                                                })
                                            : Action::make('Switch')
                                                ->button()
                                                ->icon('heroicon-m-power')
                                                ->action(function ($record) {
                                                    if (empty($record->device->command_topic)) {
                                                        return;
                                                    }

                                                    $toggledPayload = $record->device->status === $record->device->payload_off
                                                        ? $record->device->payload_on
                                                        : $record->device->payload_off;

                                                    MQTT::publish($record->device->command_topic, $toggledPayload);
                                                })
                                    ),
                                TextEntry::make('device.name')
                                    ->hidden(
                                        fn ($record) => $record->device->type === DeviceTypeEnum::Button
                                            || $record->device->type === DeviceTypeEnum::Switch
                                    )
                                    ->label(fn ($record) => $record->device->status)
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
