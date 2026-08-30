<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public function render(): View
    {
        $places = Place::query()
            ->whereHas('placeUsers', fn ($query) => $query->where('user_id', Auth::id()))
            ->withCount(['devices', 'bookings', 'accessCodes'])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.addcslashes($this->search, '%_').'%';
                $query->where('name', 'like', $term);
            })
            ->orderBy('name')
            ->get();

        return view('livewire.places.index', [
            'places' => $places,
        ])->layout('layouts.client');
    }
}
