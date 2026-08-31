<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\PlaceResource;
use App\Models\Booking;
use App\Models\Place;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BookingController extends Controller
{
    private const PER_PAGE = 20;

    private const STATUS_OPTIONS = ['all', 'current', 'future', 'past'];

    /** Chaves de filtro da tela; usadas para detectar a visita sem filtro nenhum. */
    private const FILTER_KEYS = ['place_id', 'date_from', 'date_to', 'status', 'guest', 'source'];

    /**
     * Cada filtro distingue três estados, porque a `FilterBar` desta tela
     * envia as seis chaves sempre (`sendEmptyValues`): chave ausente aplica
     * o padrão, chave presente e vazia significa "Todos" (sem filtro), e
     * chave com valor filtra.
     *
     * - `place_id` é puramente opcional. O escopo de segurança é o
     *   `whereIn('place_id', $userPlaceIds)`, não este filtro, e por isso não
     *   há fallback para o primeiro place do usuário: era ele que impedia
     *   "Todos os locais" de funcionar. Id de outro usuário é ignorado em
     *   silêncio, para o filtro não virar oráculo de places alheios.
     * - `status` usa os scopes do model, com whitelist; qualquer coisa fora
     *   dela virou `all`.
     * - As datas têm semântica de sobreposição (`date_from` -> `check_out >=`,
     *   `date_to` -> `check_in <=`), e não de intervalo contido: senão a
     *   janela padrão esconderia a estadia em curso iniciada antes dela.
     *   `date_from` assume `hoje - 7 dias` só na visita sem filtro nenhum
     *   (ver `resolveDateFrom()`). Data inválida é descartada.
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

        $dateFrom = $this->resolveDateFrom($request);
        $dateTo = $request->filled('date_to') ? $this->parseDate($request->string('date_to')->toString()) : null;
        $guest = $request->filled('guest') ? $request->string('guest')->toString() : '';
        $source = $request->filled('source') ? $request->string('source')->toString() : '';

        $status = $request->filled('status') ? $request->string('status')->toString() : 'all';
        if (! in_array($status, self::STATUS_OPTIONS, true)) {
            $status = 'all';
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
            ->when($dateFrom, fn ($query) => $query->where('check_out', '>=', $dateFrom->copy()->startOfDay()))
            ->when($dateTo, fn ($query) => $query->where('check_in', '<=', $dateTo->copy()->endOfDay()))
            ->when($status === 'current', fn ($query) => $query->current($now))
            ->when($status === 'future', fn ($query) => $query->future($now))
            ->when($status === 'past', fn ($query) => $query->past($now))
            ->when($guest !== '', function ($query) use ($guest): void {
                $term = '%'.addcslashes($guest, '%_').'%';
                $query->where('guest_name', 'like', $term);
            })
            ->when($source !== '', fn ($query) => $query->where('source', $source))
            ->orderByTimeline($now)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('bookings/index', [
            'places' => PlaceResource::collection($places),
            'bookings' => BookingResource::collection($bookings),
            'filters' => [
                'place_id' => $placeId,
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'status' => $status,
                'guest' => $guest,
                'source' => $source,
            ],
        ]);
    }

    /**
     * A janela de `hoje - 7 dias` é o estado inicial da tela, não um recorte
     * implícito sobre uma busca: ela só vale quando a requisição não traz
     * filtro nenhum. Assim `?guest=Zezinho` continua varrendo todo o
     * histórico, como antes. Isto não recria o bug do `hasAny` que motivou
     * este trabalho, porque a `FilterBar` passou a enviar sempre as seis
     * chaves — nenhum estado deixa de ser expressável pela UI, e o desvio
     * aqui só alcança URL montada à mão ou bookmark antigo.
     */
    private function resolveDateFrom(Request $request): ?CarbonInterface
    {
        if ($request->has('date_from')) {
            return $request->filled('date_from')
                ? $this->parseDate($request->string('date_from')->toString())
                : null;
        }

        if ($request->hasAny(self::FILTER_KEYS)) {
            return null;
        }

        return now()->subWeek();
    }

    private function parseDate(string $value): ?CarbonInterface
    {
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
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
