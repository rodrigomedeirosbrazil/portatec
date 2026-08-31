<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\PlaceResource;
use App\Models\Booking;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Esqueleto, mas com a query de escopo e filtros já portada 1:1 de
     * `App\Livewire\Bookings\Index::mount()` / `render()` — a UI de filtro
     * (§7 do spec, `<FilterBar>`) fica a cargo da fase de implementação
     * paralela, que só precisa consumir estes mesmos parâmetros de query.
     */
    public function index(Request $request): Response
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $placeId = null;
        if ($request->filled('place_id')) {
            $requestedId = (int) $request->input('place_id');
            if ($userPlaceIds->contains($requestedId)) {
                $placeId = $requestedId;
            }
        }
        if ($placeId === null && ! $request->has('place_id')) {
            $placeId = Auth::user()->placeUsers()->value('place_id');
        }

        $dateFrom = $request->filled('date_from') ? $request->string('date_from')->toString() : null;
        $dateTo = $request->filled('date_to') ? $request->string('date_to')->toString() : null;
        $guest = $request->filled('guest') ? $request->string('guest')->toString() : '';
        $source = $request->filled('source') ? $request->string('source')->toString() : '';

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
        } elseif (! $request->hasAny(['date_from', 'date_to', 'guest', 'source'])) {
            $status = 'future';
        } else {
            $status = '';
        }

        $places = Place::query()
            ->whereIn('id', $userPlaceIds)
            ->orderBy('name')
            ->get();

        $now = now();

        $bookings = Booking::query()
            ->with('place')
            ->whereIn('place_id', $userPlaceIds)
            ->when($placeId, fn ($query) => $query->where('place_id', $placeId))
            ->when($dateFrom, fn ($query) => $query->whereDate('check_in', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('check_out', '<=', $dateTo))
            ->when($status === 'past', fn ($query) => $query->where('check_out', '<', $now))
            ->when($status === 'current', fn ($query) => $query->where('check_in', '<=', $now)->where('check_out', '>=', $now))
            ->when($status === 'future', fn ($query) => $query->where('check_in', '>', $now))
            ->when($guest !== '', function ($query) use ($guest): void {
                $term = '%'.addcslashes($guest, '%_').'%';
                $query->where('guest_name', 'like', $term);
            })
            ->when($source !== '', fn ($query) => $query->where('source', $source))
            ->orderBy('check_in')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('bookings/index', [
            'places' => PlaceResource::collection($places),
            'bookings' => BookingResource::collection($bookings),
            'filters' => [
                'place_id' => $placeId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => $status,
                'guest' => $guest,
                'source' => $source,
            ],
        ]);
    }

    /**
     * Ported 1:1 from `App\Livewire\Bookings\Create::mount()` / `render()`:
     * defaults `placeId` to the user's first place.
     */
    public function create(Request $request): Response
    {
        $placeId = Auth::user()->placeUsers()->value('place_id');

        $places = Place::query()
            ->whereHas('placeUsers', fn ($query) => $query->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get();

        return Inertia::render('bookings/create', [
            'places' => PlaceResource::collection($places),
            'placeId' => $placeId,
        ]);
    }

    /**
     * Ported 1:1 from `App\Livewire\Bookings\Create::save()`: validation
     * lives in `StoreBookingRequest`, but the place-ownership check must
     * still happen here before creating the record.
     */
    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $hasAccess = Auth::user()
            ->placeUsers()
            ->where('place_id', $validated['placeId'])
            ->exists();

        abort_unless($hasAccess, 403);

        $booking = Booking::create([
            'place_id' => $validated['placeId'],
            'guest_name' => $validated['guestName'] ?? null,
            'check_in' => $validated['checkIn'],
            'check_out' => $validated['checkOut'],
            'source' => 'manual',
        ]);

        return redirect()
            ->route('app.bookings.show', ['booking' => $booking->id])
            ->with('status', __('app.booking_created'));
    }

    /**
     * `{booking}` comes raw from the URL via route-model-binding: unlike
     * Livewire's signed snapshot, authorization must happen here first —
     * see the phase-4 IDOR lesson. Ported 1:1 from
     * `App\Livewire\Bookings\Show::mount()` / `render()` otherwise.
     */
    public function show(Request $request, Booking $booking): Response
    {
        abort_unless(Auth::user()?->can('view', $booking), 403);

        $booking->load(['accessCode', 'place']);

        return Inertia::render('bookings/show', [
            'booking' => new BookingResource($booking),
            'canDelete' => $booking->source === 'manual',
        ]);
    }

    /**
     * Same note as `show()` about authorizing the URL model first. Ported
     * 1:1 from `App\Livewire\Bookings\Show::deleteBooking()`.
     */
    public function destroy(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless(Auth::user()?->can('delete', $booking), 403);

        abort_unless($booking->source === 'manual', 403);

        $booking->delete();

        return redirect()
            ->route('app.bookings.index')
            ->with('status', __('app.booking_deleted'));
    }
}
