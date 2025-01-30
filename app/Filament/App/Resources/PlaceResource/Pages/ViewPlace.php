<?php

namespace App\Filament\App\Resources\PlaceResource\Pages;

use App\Filament\App\Resources\PlaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Support\HtmlString;

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
                            TextEntry::make('placeDevices.device.name')
                            ->label('Devices')
                            ->formatStateUsing(
                                fn ($record): HtmlString => new HtmlString(
                                    '<ul class="list-disc list-inside">' .
                                    $record->placeDevices->map(
                                        fn ($placeDevice) => '<li>' . e($placeDevice->device->name) . '</li>'
                                    )->implode('') .
                                    '</ul>'
                                )
                            ),
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
