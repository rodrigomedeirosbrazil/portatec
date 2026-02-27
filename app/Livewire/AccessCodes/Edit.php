<?php

declare(strict_types=1);

namespace App\Livewire\AccessCodes;

use App\Models\AccessCode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Edit extends Component
{
    public AccessCode $accessCode;
    public ?string $label = null;
    public string $pin = '';
    public string $start = '';
    public ?string $end = null;

    public function mount(int $accessCode): void
    {
        $this->accessCode = AccessCode::findOrFail($accessCode);

        abort_unless(
            Auth::user()->placeUsers()->where('place_id', $this->accessCode->place_id)->exists(),
            403
        );

        $this->label = $this->accessCode->label;
        $this->pin = $this->accessCode->pin;
        $this->start = $this->accessCode->start?->format('Y-m-d\TH:i') ?? '';
        $this->end = $this->accessCode->end?->format('Y-m-d\TH:i');
    }

    protected function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'pin' => ['required', 'string', 'max:6'],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after:start'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        $this->accessCode->update([
            'label' => $validated['label'],
            'pin' => $validated['pin'],
            'start' => $validated['start'],
            'end' => $validated['end'],
        ]);

        session()->flash('status', 'Access code atualizado com sucesso.');
    }

    public function render(): View
    {
        return view('livewire.access-codes.edit')->layout('layouts.client');
    }
}
