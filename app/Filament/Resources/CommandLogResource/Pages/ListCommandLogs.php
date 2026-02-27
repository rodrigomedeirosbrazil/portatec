<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommandLogResource\Pages;

use App\Filament\Resources\CommandLogResource;
use Filament\Resources\Pages\ListRecords;

class ListCommandLogs extends ListRecords
{
    protected static string $resource = CommandLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
