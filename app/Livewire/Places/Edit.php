<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Edit extends Component
{
    public Place $place;
    public string $name = '';

    public function mount(Place $place): void
    {
        $this->place = $place;

        abort_unless(
            $this->place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );

        $this->name = $this->place->name;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        $this->place->update([
            'name' => $validated['name'],
        ]);

        session()->flash('status', 'Place atualizado com sucesso.');

        return $this->redirectRoute('app.places.show', ['place' => $this->place->id], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.places.edit')->layout('layouts.client');
    }
}
