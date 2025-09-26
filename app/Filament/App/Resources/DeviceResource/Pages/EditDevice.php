<?php

namespace App\Filament\App\Resources\DeviceResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\App\Resources\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
