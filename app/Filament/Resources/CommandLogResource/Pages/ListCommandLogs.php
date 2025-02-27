<?php

namespace App\Filament\Resources\CommandLogResource\Pages;

use App\Filament\Resources\CommandLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommandLogs extends ListRecords
{
    protected static string $resource = CommandLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
