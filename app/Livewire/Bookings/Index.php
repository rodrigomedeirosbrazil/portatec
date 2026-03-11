<?php

declare(strict_types=1);

namespace App\Livewire\Bookings;

use App\Models\Booking;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public ?int $placeId = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $status = '';

    public string $guest = '';

    public string $source = '';

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

        if (request()->filled('date_from')) {
            $this->dateFrom = request()->string('date_from')->toString();
        }
        if (request()->filled('date_to')) {
            $this->dateTo = request()->string('date_to')->toString();
        }
        if (request()->filled('status')) {
            $this->status = request()->string('status')->toString();
        }
        if (request()->filled('guest')) {
            $this->guest = request()->string('guest')->toString();
        }
        if (request()->filled('source')) {
            $this->source = request()->string('source')->toString();
        }
    }

    public function updatedPlaceId(): mixed
    {
        return $this->redirectWithFilters();
    }

    public function updatedDateFrom(): mixed
    {
        return $this->redirectWithFilters();
    }

    public function updatedDateTo(): mixed
    {
        return $this->redirectWithFilters();
    }

    public function updatedStatus(): mixed
    {
        return $this->redirectWithFilters();
    }

    public function updatedSource(): mixed
    {
        return $this->redirectWithFilters();
    }

    public function updatedGuest(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $places = Place::query()
            ->whereIn('id', $userPlaceIds)
            ->orderBy('name')
            ->get();

        $now = now();

        $bookings = Booking::query()
            ->whereIn('place_id', $userPlaceIds)
            ->when($this->placeId, fn ($query) => $query->where('place_id', $this->placeId))
            ->when($this->dateFrom, function ($query): void {
                $query->whereDate('check_in', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query): void {
                $query->whereDate('check_out', '<=', $this->dateTo);
            })
            ->when($this->status === 'past', fn ($query) => $query->where('check_out', '<', $now))
            ->when($this->status === 'current', function ($query) use ($now): void {
                $query->where('check_in', '<=', $now)->where('check_out', '>=', $now);
            })
            ->when($this->status === 'future', fn ($query) => $query->where('check_in', '>', $now))
            ->when($this->guest !== '', function ($query): void {
                $term = '%' . addcslashes($this->guest, '%_') . '%';
                $query->where('guest_name', 'like', $term);
            })
            ->when($this->source !== '', fn ($query) => $query->where('source', $this->source))
            ->orderBy('check_in')
            ->paginate(self::PER_PAGE, ['*'], 'page', $this->getPage());

        return view('livewire.bookings.index', [
            'places' => $places,
            'bookings' => $bookings,
        ])->layout('layouts.client');
    }

    private function redirectWithFilters(): mixed
    {
        $this->resetPage();

        $params = array_filter([
            'place_id' => $this->placeId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'status' => $this->status !== '' ? $this->status : null,
            'guest' => $this->guest !== '' ? $this->guest : null,
            'source' => $this->source !== '' ? $this->source : null,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->to(route('app.bookings.index', $params));
    }
}
