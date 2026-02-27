<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public Place $place;

    public function mount(int $place): void
    {
        $this->place = Place::query()
            ->with(['devices', 'bookings' => fn ($q) => $q->latest('check_in')->limit(10), 'accessCodes'])
            ->findOrFail($place);

        abort_unless(
            $this->place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );
    }

    public function render(): View
    {
        $activeAccessCodes = $this->place->accessCodes()
            ->where('start', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end')->orWhere('end', '>=', now());
            })
            ->count();

        return view('livewire.places.show', [
            'activeAccessCodes' => $activeAccessCodes,
        ])->layout('layouts.client');
    }
}
