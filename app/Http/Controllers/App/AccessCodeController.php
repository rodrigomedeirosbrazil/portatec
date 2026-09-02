<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccessCodeRequest;
use App\Http\Requests\UpdateAccessCodeRequest;
use App\Http\Resources\AccessCodeResource;
use App\Http\Resources\PlaceResource;
use App\Models\AccessCode;
use App\Models\Place;
use App\Services\AccessCode\AccessCodeGeneratorService;
use App\Services\CurrentPlaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccessCodeController extends Controller
{
    private const PER_PAGE = 20;

    private const STATUS_OPTIONS = ['', 'active', 'future', 'expired'];

    /**
     * `place_id` é resolvido via `CurrentPlaceService`, que usa o local atual
     * guardado em sessão (com precedência de um `place_id` explícito na query
     * string, que atualiza a sessão). O escopo de segurança continua sendo o
     * `whereIn('place_id', $userPlaceIds)`, não este filtro.
     * `status` usa whitelist; qualquer coisa fora dela vira "todos".
     * Ported 1:1 from `App\Livewire\AccessCodes\Index::mount()` / `render()`.
     */
    public function index(Request $request, CurrentPlaceService $currentPlace): Response
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $placeId = $currentPlace->resolveForRequest($request, Auth::user());

        $status = $request->filled('status') ? $request->string('status')->toString() : '';
        if (! in_array($status, self::STATUS_OPTIONS, true)) {
            $status = '';
        }
        $search = $request->filled('search') ? $request->string('search')->toString() : '';

        $places = Place::query()
            ->whereIn('id', $userPlaceIds)
            ->orderBy('name')
            ->get();

        $now = now();

        $accessCodes = AccessCode::query()
            ->with('place')
            ->whereIn('place_id', $userPlaceIds)
            ->when($placeId, fn ($query) => $query->where('place_id', $placeId))
            ->when($status === 'active', function ($query) use ($now): void {
                $query->where('start', '<=', $now)
                    ->where(fn ($q) => $q->whereNull('end')->orWhere('end', '>=', $now));
            })
            ->when($status === 'expired', function ($query) use ($now): void {
                $query->whereNotNull('end')->where('end', '<', $now);
            })
            ->when($status === 'future', fn ($query) => $query->where('start', '>', $now))
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_').'%';
                $query->where('pin', 'like', $term);
            })
            ->orderBy('start', 'desc')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('access-codes/index', [
            'places' => PlaceResource::collection($places),
            'accessCodes' => AccessCodeResource::collection($accessCodes),
            'filters' => [
                'place_id' => $placeId,
                'status' => $status,
                'search' => $search,
            ],
            'now' => $now->toIso8601String(),
        ]);
    }

    /**
     * Ported 1:1 from `App\Livewire\AccessCodes\Create::mount()` / `render()`.
     */
    public function create(Request $request): Response
    {
        $placeId = Auth::user()->placeUsers()->value('place_id');

        $places = Place::query()
            ->whereHas('placeUsers', fn ($query) => $query->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get();

        return Inertia::render('access-codes/create', [
            'places' => PlaceResource::collection($places),
            'placeId' => $placeId,
        ]);
    }

    /**
     * Ported 1:1 from `App\Livewire\AccessCodes\Create::save()`: the
     * place-ownership check happens here, before the record is created via
     * `AccessCodeGeneratorService::createStandalone()` (which triggers the
     * `AccessCodeObserver` for device sync). Redirects to the new code's
     * edit screen, matching the original — there is no "show" screen.
     */
    public function store(StoreAccessCodeRequest $request, AccessCodeGeneratorService $generator): RedirectResponse
    {
        $validated = $request->validated();

        $hasAccess = Auth::user()
            ->placeUsers()
            ->where('place_id', $validated['placeId'])
            ->exists();

        abort_unless($hasAccess, 403);

        $accessCode = $generator->createStandalone(
            placeId: $validated['placeId'],
            userId: null,
            start: Carbon::parse($validated['start']),
            end: isset($validated['end']) ? Carbon::parse($validated['end']) : null,
            pin: $validated['pin'] ?? null
        );

        return redirect()
            ->route('app.access-codes.edit', ['accessCode' => $accessCode->id])
            ->with('status', trans('app.access_code_created'));
    }

    /**
     * `{accessCode}` comes raw from the URL via route-model-binding, so it
     * must be authorized here first — see the phase-4 IDOR lesson. Ported
     * 1:1 from `App\Livewire\AccessCodes\Edit::mount()` / `render()`
     * otherwise, including the `Y-m-d\TH:i` formatting the datetime-local
     * inputs rely on.
     */
    public function edit(Request $request, AccessCode $accessCode): Response
    {
        abort_unless(Auth::user()?->can('update', $accessCode), 403);

        return Inertia::render('access-codes/edit', [
            'accessCode' => new AccessCodeResource($accessCode),
            'pin' => $accessCode->pin,
            'start' => $accessCode->start?->format('Y-m-d\TH:i') ?? '',
            'end' => $accessCode->end?->format('Y-m-d\TH:i'),
        ]);
    }

    /**
     * Same authorization note as `edit()`. Ported 1:1 from
     * `App\Livewire\AccessCodes\Edit::save()`: uses `$accessCode->update()`
     * (a model operation, not the query builder) so the `AccessCodeObserver`
     * fires and PINs keep reaching the physical locks. Unlike a typical
     * update, this redirects back to the same edit screen rather than to an
     * index/show, matching the original component's behavior of staying put
     * and flashing a success message.
     */
    public function update(UpdateAccessCodeRequest $request, AccessCode $accessCode): RedirectResponse
    {
        abort_unless(Auth::user()?->can('update', $accessCode), 403);

        $validated = $request->validated();

        $accessCode->update([
            'pin' => $validated['pin'],
            'start' => $validated['start'],
            'end' => $validated['end'],
        ]);

        return redirect()
            ->route('app.access-codes.edit', ['accessCode' => $accessCode->id])
            ->with('status', trans('app.access_code_updated'));
    }
}
