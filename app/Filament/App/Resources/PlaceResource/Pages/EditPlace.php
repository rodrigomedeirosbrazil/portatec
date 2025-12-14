<?php

namespace App\Filament\App\Resources\PlaceResource\Pages;

use App\Enums\PlaceRoleEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use App\Filament\App\Resources\PlaceResource;
use App\Models\Place;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditPlace extends EditRecord
{
    protected static string $resource = PlaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('control-devices')
                ->label(__('app.control_devices'))
                ->icon('heroicon-o-cog-6-tooth')
                ->url(fn (): string => route('places.devices', $this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool =>
                    auth()->user()->hasRole('super_admin') ||
                    $this->record->placeUsers()
                        ->where('user_id', auth()->user()->id)
                        ->whereIn('role', [PlaceRoleEnum::Admin, PlaceRoleEnum::Host])
                        ->exists()
                ),
            Action::make('duplicate')
                ->label('Duplicar')
                ->icon('heroicon-o-document-duplicate')
                ->action(function (Place $record) {
                    return DB::transaction(function () use ($record) {
                        // Create a copy of the place
                        $newPlace = $record->replicate();
                        $newPlace->name = $record->name.' (Cópia)';
                        $newPlace->save();

                        foreach ($record->placeUsers as $placeUser) {
                            $newPlaceUser = $placeUser->replicate();
                            $newPlaceUser->place_id = $newPlace->id;
                            $newPlaceUser->save();
                        }

                        foreach ($record->placeDevices as $placeDevice) {
                            $newPlaceDevice = $placeDevice->replicate();
                            $newPlaceDevice->place_id = $newPlace->id;
                            $newPlaceDevice->save();
                        }

                        return redirect()->route('filament.app.resources.places.edit', ['record' => $newPlace]);
                    });
                }),
            DeleteAction::make(),
        ];
    }
}
