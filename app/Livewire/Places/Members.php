<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Enums\PlaceRoleEnum;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Members extends Component
{
    public Place $place;

    public ?int $addUserId = null;

    public string $addRole = 'host';

    public string $addLabel = '';

    public string $userSearch = '';

    public function mount(Place $place): void
    {
        $this->place = $place->load(['placeUsers.user']);

        $this->authorize('manageMembers', $this->place);
    }

    public function getUsersNotInPlaceProperty(): \Illuminate\Support\Collection
    {
        if (strlen($this->userSearch) < 2) {
            return collect();
        }

        $existingIds = $this->place->placeUsers->pluck('user_id')->all();
        $term = '%'.addcslashes($this->userSearch, '%_').'%';

        return User::query()
            ->whereNotIn('id', $existingIds)
            ->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            })
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    protected function rules(): array
    {
        return [
            'addUserId' => ['required', 'integer', 'exists:users,id'],
            'addRole' => ['required', 'string', 'in:admin,host'],
            'addLabel' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function selectUser(int $id): void
    {
        $exists = $this->place->placeUsers()->where('user_id', $id)->exists();
        if ($exists) {
            return;
        }
        $user = User::query()->find($id);
        if ($user instanceof User) {
            $this->addUserId = $id;
        }
    }

    public function clearSelectedUser(): void
    {
        $this->addUserId = null;
        $this->userSearch = '';
        $this->resetErrorBag('addUserId');
    }

    public function addMember(): void
    {
        $this->validate();

        $exists = $this->place->placeUsers()
            ->where('user_id', $this->addUserId)
            ->exists();

        if ($exists) {
            $this->addError('addUserId', __('app.member_already_in_place'));

            return;
        }

        PlaceUser::create([
            'place_id' => $this->place->id,
            'user_id' => $this->addUserId,
            'role' => $this->addRole,
            'label' => $this->addLabel ?: null,
        ]);

        $this->place->load(['placeUsers.user']);
        $this->clearSelectedUser();
        $this->addRole = 'host';
        $this->addLabel = '';

        session()->flash('status', __('app.member_added'));
    }

    public function removeMember(int $placeUserId): void
    {
        $placeUser = PlaceUser::query()
            ->where('place_id', $this->place->id)
            ->findOrFail($placeUserId);

        if ($placeUser->role === PlaceRoleEnum::Admin->value) {
            $adminCount = $this->place->placeUsers()
                ->where('role', PlaceRoleEnum::Admin->value)
                ->count();

            if ($adminCount <= 1) {
                session()->flash('error', __('app.cannot_remove_last_admin'));

                return;
            }
        }

        $placeUser->delete();
        $this->place->load(['placeUsers.user']);

        session()->flash('status', __('app.member_removed'));
    }

    public function render(): View
    {
        $selectedUser = $this->addUserId !== null
            ? User::query()->find($this->addUserId)
            : null;

        return view('livewire.places.members', [
            'placeUsers' => $this->place->placeUsers,
            'usersNotInPlace' => $this->usersNotInPlace,
            'placeRoles' => PlaceRoleEnum::toArray(),
            'selectedUser' => $selectedUser,
        ])->layout('layouts.client');
    }
}
