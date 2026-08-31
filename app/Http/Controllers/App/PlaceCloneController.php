<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\PlaceRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceCloneRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Place;
use App\Models\User;
use App\Services\PlaceCloneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PlaceCloneController extends Controller
{
    public function create(Place $place): Response
    {
        $this->authorize('replicate', $place);

        $place->load(['devices.deviceFunctions']);

        $usersForSelect = User::query()
            ->where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get();

        return Inertia::render('places/clone', [
            'place' => new PlaceResource($place),
            'suggestedName' => __('app.clone_place_suggested_name', ['name' => $place->name]),
            'placeRoles' => PlaceRoleEnum::toArray(),
            'users' => $usersForSelect->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ]);
    }

    public function store(StorePlaceCloneRequest $request, Place $place, PlaceCloneService $service): RedirectResponse
    {
        $this->authorize('replicate', $place);

        $validated = $request->validated();

        $authId = (int) Auth::id();

        $members = [];
        foreach ($validated['additionalMembers'] ?? [] as $row) {
            $userId = is_numeric($row['user_id'] ?? '') ? (int) $row['user_id'] : 0;
            if ($userId === 0 || $userId === $authId) {
                continue;
            }
            $members[] = [
                'user_id' => $userId,
                'role' => $row['role'] ?? PlaceRoleEnum::Host->value,
                'label' => ! empty($row['label']) ? $row['label'] : null,
            ];
        }

        $newPlace = $service->clone(
            $place,
            $validated['name'],
            $authId,
            $members
        );

        return redirect()
            ->route('app.places.show', ['place' => $newPlace->id])
            ->with('status', __('app.place_cloned'));
    }
}
