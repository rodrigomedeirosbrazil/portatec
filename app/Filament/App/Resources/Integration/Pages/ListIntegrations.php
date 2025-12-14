<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Integration\Pages;

use App\Filament\App\Resources\Integration\IntegrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIntegrations extends ListRecords
{
    protected static string $resource = IntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
