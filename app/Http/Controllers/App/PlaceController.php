<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceRequest;
use App\Http\Requests\UpdatePlaceRequest;
use App\Http\Resources\IntegrationResource;
use App\Http\Resources\PlaceResource;
use App\Models\Place;
use App\Models\PlaceUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = (string) $request->query('search', '');

        $places = Place::query()
            ->whereHas('placeUsers', fn ($query) => $query->where('user_id', Auth::id()))
            ->withCount(['devices', 'bookings', 'accessCodes'])
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_').'%';
                $query->where('name', 'like', $term);
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('places/index', [
            'places' => PlaceResource::collection($places),
            'search' => $search,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('places/create');
    }

    public function store(StorePlaceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        $place = Place::create([
            'name' => $validated['name'],
        ]);

        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);

        return redirect()
            ->route('app.places.show', ['place' => $place->id])
            ->with('status', trans('app.place_created'));
    }

    public function show(Request $request, Place $place): Response
    {
        $place->load([
            'devices',
            'bookings' => fn ($query) => $query->latest('check_in')->limit(10),
            'accessCodes',
            'placeUsers.user',
            'integrations' => fn ($query) => $query->whereHas(
                'platform',
                fn ($q) => $q->where('slug', '!=', 'tuya')
            )->with('platform'),
        ]);

        abort_unless(
            $place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );

        $place->loadCount('bookings');

        $activeAccessCodes = $place->accessCodes()
            ->where('start', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end')->orWhere('end', '>=', now());
            })
            ->count();

        return Inertia::render('places/show', [
            'place' => new PlaceResource($place),
            'activeAccessCodes' => $activeAccessCodes,
            'bookingsCount' => $place->bookings_count,
            'bookingSources' => IntegrationResource::collection($place->integrations),
            'abilities' => [
                'manageMembers' => Auth::user()?->can('manageMembers', $place) ?? false,
                'replicate' => Auth::user()?->can('replicate', $place) ?? false,
                'update' => Auth::user()?->can('update', $place) ?? false,
            ],
        ]);
    }

    public function edit(Request $request, Place $place): Response
    {
        abort_unless(
            $place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );

        return Inertia::render('places/edit', [
            'place' => new PlaceResource($place),
        ]);
    }

    public function update(UpdatePlaceRequest $request, Place $place): RedirectResponse
    {
        abort_unless(
            $place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );

        $validated = $request->validated();

        $place->update([
            'name' => $validated['name'],
        ]);

        return redirect()
            ->route('app.places.show', ['place' => $place->id])
            ->with('status', trans('app.place_updated'));
    }
}
