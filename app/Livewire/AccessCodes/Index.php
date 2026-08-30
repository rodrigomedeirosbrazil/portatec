<?php

declare(strict_types=1);

namespace App\Livewire\AccessCodes;

use App\Models\AccessCode;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public ?int $placeId = null;

    public string $status = '';

    public string $search = '';

    private const PER_PAGE = 20;

    public function mount(): void
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        if (request()->has('place_id')) {
            $requestedId = (int) request()->input('place_id');
            if ($userPlaceIds->contains($requestedId)) {
                $this->placeId = $requestedId;
            }
        }

        if ($this->placeId === null) {
            $this->placeId = Auth::user()->placeUsers()->value('place_id');
        }

        if (request()->filled('status')) {
            $this->status = request()->string('status')->toString();
        }
    }

    public function updatedPlaceId()
    {
        $this->resetPage();

        $params = array_filter([
            'place_id' => $this->placeId,
            'status' => $this->status !== '' ? $this->status : null,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->to(route('app.access-codes.index', $params));
    }

    public function updatedStatus()
    {
        $this->resetPage();

        $params = array_filter([
            'place_id' => $this->placeId,
            'status' => $this->status !== '' ? $this->status : null,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->to(route('app.access-codes.index', $params));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');
        $now = now();

        $places = Place::query()
            ->whereIn('id', $userPlaceIds)
            ->orderBy('name')
            ->get();

        $accessCodes = AccessCode::query()
            ->whereIn('place_id', $userPlaceIds)
            ->when($this->placeId, fn ($query) => $query->where('place_id', $this->placeId))
            ->when($this->status === 'active', function ($query) use ($now): void {
                $query->where('start', '<=', $now)
                    ->where(fn ($q) => $q->whereNull('end')->orWhere('end', '>=', $now));
            })
            ->when($this->status === 'expired', function ($query) use ($now): void {
                $query->whereNotNull('end')->where('end', '<', $now);
            })
            ->when($this->status === 'future', fn ($query) => $query->where('start', '>', $now))
            ->when($this->search !== '', function ($query): void {
                $term = '%'.addcslashes($this->search, '%_').'%';
                $query->where('pin', 'like', $term);
            })
            ->orderBy('start', 'desc')
            ->paginate(self::PER_PAGE);

        return view('livewire.access-codes.index', [
            'places' => $places,
            'accessCodes' => $accessCodes,
            'now' => $now,
        ])->layout('layouts.client');
    }
}
