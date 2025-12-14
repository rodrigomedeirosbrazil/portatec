<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Booking\Pages;

use App\Filament\App\Resources\Booking\BookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
