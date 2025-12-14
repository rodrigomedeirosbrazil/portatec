<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Platform\Pages;

use App\Filament\App\Resources\Platform\PlatformResource;
use Filament\Resources\Pages\ListRecords;

class ListPlatforms extends ListRecords
{
    protected static string $resource = PlatformResource::class;
}
