<?php

declare(strict_types=1);

namespace App\Livewire\AccessCodes;

use App\Models\Place;
use App\Services\AccessCode\AccessCodeGeneratorService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Create extends Component
{
    public ?int $placeId = null;
    public ?string $label = null;
    public ?string $pin = null;
    public string $start = '';
    public ?string $end = null;

    public function mount(): void
    {
        if ($this->placeId === null) {
            $this->placeId = Auth::user()->placeUsers()->value('place_id');
        }
    }

    protected function rules(): array
    {
        return [
            'placeId' => ['required', 'integer', 'exists:places,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'pin' => ['nullable', 'string', 'max:6'],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after:start'],
        ];
    }

    public function save(AccessCodeGeneratorService $generator)
    {
        $validated = $this->validate();

        $hasAccess = Auth::user()
            ->placeUsers()
            ->where('place_id', $validated['placeId'])
            ->exists();

        abort_unless($hasAccess, 403);

        $accessCode = $generator->createStandalone(
            placeId: $validated['placeId'],
            userId: null,
            label: $validated['label'],
            start: Carbon::parse($validated['start']),
            end: isset($validated['end']) ? Carbon::parse($validated['end']) : null,
            pin: $validated['pin']
        );

        session()->flash('status', 'Access code criado com sucesso.');

        return $this->redirectRoute('app.access-codes.edit', ['accessCode' => $accessCode->id], navigate: true);
    }

    public function render(): View
    {
        $places = Place::query()
            ->whereHas('placeUsers', fn ($query) => $query->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get();

        return view('livewire.access-codes.create', [
            'places' => $places,
        ])->layout('layouts.client');
    }
}
