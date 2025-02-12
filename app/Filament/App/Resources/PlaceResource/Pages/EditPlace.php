<?php

namespace App\Filament\App\Resources\PlaceResource\Pages;

use App\Enums\PlaceRoleEnum;
use App\Filament\App\Resources\PlaceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlace extends EditRecord
{
    protected static string $resource = PlaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function hasValidRecord(): bool
    {
        $hasValidRecord = parent::hasValidRecord();

        if (!$hasValidRecord) {
            return false;
        }

        return auth()->user()->hasRole('super_admin') ||
            $this->record->placeUsers()
                ->where('user_id', auth()->user()->id)
                ->where('role', PlaceRoleEnum::Admin)
                ->exists();
    }
}
