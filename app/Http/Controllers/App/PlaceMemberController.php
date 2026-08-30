<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\PlaceRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceMemberRequest;
use App\Http\Resources\PlaceResource;
use App\Http\Resources\PlaceUserResource;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Services\PlaceMemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaceMemberController extends Controller
{
    public function index(Request $request, Place $place): Response
    {
        $this->authorize('manageMembers', $place);

        $place->load(['placeUsers.user']);

        return Inertia::render('places/members', [
            'place' => new PlaceResource($place),
            'placeUsers' => PlaceUserResource::collection($place->placeUsers),
            'placeRoles' => PlaceRoleEnum::toArray(),
        ]);
    }

    public function store(StorePlaceMemberRequest $request, Place $place, PlaceMemberService $service): RedirectResponse
    {
        $this->authorize('manageMembers', $place);

        $validated = $request->validated();

        $service->create(
            $place,
            (int) $validated['user_id'],
            $validated['role'],
            $validated['label'] ?: null
        );

        return redirect()
            ->route('app.places.members', ['place' => $place->id])
            ->with('status', __('app.member_added'));
    }

    public function destroy(Request $request, Place $place, int $placeUser, PlaceMemberService $service): RedirectResponse
    {
        $this->authorize('manageMembers', $place);

        $member = PlaceUser::query()
            ->where('place_id', $place->id)
            ->findOrFail($placeUser);

        if (! $service->remove($place, $member)) {
            return redirect()
                ->route('app.places.members', ['place' => $place->id])
                ->withErrors(['member' => __('app.cannot_remove_last_admin')]);
        }

        return redirect()
            ->route('app.places.members', ['place' => $place->id])
            ->with('status', __('app.member_removed'));
    }
}
