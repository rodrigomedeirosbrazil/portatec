<?php

namespace App\Filament\App\Resources\DeviceResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\App\Resources\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
