<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Place;
use App\Models\PlaceUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Create extends Component
{
    public string $name = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();
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

        session()->flash('status', 'Place criado com sucesso.');

        return $this->redirectRoute('app.places.show', ['place' => $place->id], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.places.create')->layout('layouts.client');
    }
}
