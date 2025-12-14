<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Integration\Pages;

use App\Filament\App\Resources\Integration\IntegrationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIntegration extends CreateRecord
{
    protected static string $resource = IntegrationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
