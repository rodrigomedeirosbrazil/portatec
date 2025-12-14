<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\AccessCodes\Pages;

use App\Filament\App\Resources\AccessCodes\AccessCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAccessCodes extends ManageRecords
{
    protected static string $resource = AccessCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
