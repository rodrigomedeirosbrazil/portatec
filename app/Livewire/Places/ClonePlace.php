<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Enums\PlaceRoleEnum;
use App\Models\Place;
use App\Models\User;
use App\Services\PlaceCloneService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ClonePlace extends Component
{
    public Place $place;

    public string $name = '';

    /** @var array<int, array{user_id: string, role: string, label: string}> */
    public array $additionalMembers = [];

    public function mount(Place $place): void
    {
        $this->place = $place->load(['devices.deviceFunctions']);

        $this->authorize('replicate', $this->place);

        $this->name = 'Cópia de '.$place->name;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'additionalMembers' => ['array'],
            'additionalMembers.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'additionalMembers.*.role' => ['required', 'string', 'in:admin,host'],
            'additionalMembers.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addMemberRow(): void
    {
        $this->additionalMembers[] = [
            'user_id' => '',
            'role' => PlaceRoleEnum::Host->value,
            'label' => '',
        ];
    }

    public function removeMemberRow(int $index): void
    {
        unset($this->additionalMembers[$index]);
        $this->additionalMembers = array_values($this->additionalMembers);
    }

    public function clonePlace(PlaceCloneService $service): mixed
    {
        $this->validate();

        $members = [];
        foreach ($this->additionalMembers as $row) {
            $userId = is_numeric($row['user_id'] ?? '') ? (int) $row['user_id'] : 0;
            if ($userId === 0 || $userId === (int) Auth::id()) {
                continue;
            }
            $members[] = [
                'user_id' => $userId,
                'role' => $row['role'] ?? PlaceRoleEnum::Host->value,
                'label' => ! empty($row['label']) ? $row['label'] : null,
            ];
        }

        $newPlace = $service->clone(
            $this->place,
            $this->name,
            (int) Auth::id(),
            $members
        );

        session()->flash('status', __('app.place_cloned'));

        return $this->redirectRoute('app.places.show', ['place' => $newPlace->id], navigate: true);
    }

    public function render(): View
    {
        $usersForSelect = User::query()
            ->where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get();

        return view('livewire.places.clone', [
            'placeRoles' => PlaceRoleEnum::toArray(),
            'usersForSelect' => $usersForSelect,
        ])->layout('layouts.client');
    }
}
