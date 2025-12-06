<?php

namespace App\Filament\App\Resources\AccessPins\Pages;

use App\Filament\App\Resources\AccessPins\AccessPinResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAccessPins extends ManageRecords
{
    protected static string $resource = AccessPinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
