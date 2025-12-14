<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\AccessEvent\Pages;

use App\Filament\App\Resources\AccessEvent\AccessEventResource;
use Filament\Resources\Pages\ListRecords;

class ListAccessEvents extends ListRecords
{
    protected static string $resource = AccessEventResource::class;
}
