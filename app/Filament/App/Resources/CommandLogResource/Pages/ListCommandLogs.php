<?php

namespace App\Filament\App\Resources\CommandLogResource\Pages;

use App\Filament\App\Resources\CommandLogResource;
use Filament\Resources\Pages\ListRecords;

class ListCommandLogs extends ListRecords
{
    protected static string $resource = CommandLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
