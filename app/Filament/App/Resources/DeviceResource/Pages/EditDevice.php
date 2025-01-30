<?php

namespace App\Filament\App\Resources\DeviceResource\Pages;

use App\Filament\App\Resources\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
