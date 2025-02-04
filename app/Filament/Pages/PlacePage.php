<?php

namespace App\Filament\Pages;

use App\Models\Place;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

class PlacePage extends Page
{
    public Place $place;
    public ?string $token;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.place';

    public function mount(int $id): void
    {
        $this->place = Place::findOrFail($id);
        $this->token = null;

        if (! $this->userCanAccess()) {
            abort(403);
        }
    }

    public function userCanAccess(): bool
    {
        return auth()->check()
            && (auth()->user()->hasRole('super_admin')
                || $this->place->hasAccessToPlace(auth()->user()));
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
