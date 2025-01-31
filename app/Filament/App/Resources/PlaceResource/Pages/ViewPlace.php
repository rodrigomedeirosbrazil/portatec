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
                                    ->label(fn ($record) => $record->device->type->value)
                                    ->suffixAction(
                                        fn ($record) => $record->device->type === DeviceTypeEnum::Button
                                            ? Action::make('pushButton')
                                                ->button()
                                                ->icon('heroicon-m-play')
                                                ->action(function ($record) {
                                                    dd('Push button clicked');
                                                })
                                            : Action::make('Sensor')
                                                ->button()
                                                ->icon('heroicon-m-power')
                                                ->action(function ($record) {
                                                    dd('Sensor clicked');
                                                })
                                    ),
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
