<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccessEventResource\Pages;

use App\Filament\Resources\AccessEventResource;
use Filament\Resources\Pages\ListRecords;

class ListAccessEvents extends ListRecords
{
    protected static string $resource = AccessEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
