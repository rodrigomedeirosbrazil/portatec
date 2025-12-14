<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Integration\Pages;

use App\Filament\App\Resources\Integration\IntegrationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIntegration extends EditRecord
{
    protected static string $resource = IntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
