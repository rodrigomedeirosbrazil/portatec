<?php

namespace App\Filament\App\Resources\PlaceResource\Pages;

use App\Filament\App\Resources\PlaceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Place;
use Illuminate\Support\Facades\DB;

class EditPlace extends EditRecord
{
    protected static string $resource = PlaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('duplicate')
                ->label('Duplicar')
                ->icon('heroicon-o-document-duplicate')
                ->action(function (Place $record) {
                    return DB::transaction(function () use ($record) {
                        // Create a copy of the place
                        $newPlace = $record->replicate();
                        $newPlace->name = $record->name . ' (Cópia)';
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
            Actions\DeleteAction::make(),
        ];
    }
}
