<?php

namespace App\Filament\Pages;

use App\Models\Place;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

class PlacePage extends Page
{
    public Place $place;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.place';

    public function mount(int $id): void
    {
        $this->place = Place::findOrFail($id);
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function getTitle(): string | Htmlable
    {
        return $this->place->name;
    }
}
