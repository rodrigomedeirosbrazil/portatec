<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Models\Integration;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Edit extends Component
{
    public Integration $integration;

    public array $externalIds = [];

    public function mount(Integration $integration): void
    {
        abort_unless((int) $integration->user_id === (int) Auth::id(), 403);

        $this->integration = $integration->load(['platform', 'places']);
        abort_if($this->integration->platform?->slug === 'tuya', 404);
        $this->externalIds = $this->integration->places
            ->mapWithKeys(fn (Place $place) => [$place->id => $place->pivot->external_id])
            ->toArray();
    }

    public function updateExternalId(int $placeId): void
    {
        if (! array_key_exists($placeId, $this->externalIds)) {
            $this->addError('externalIds.'.$placeId, 'Place inválido.');

            return;
        }

        $value = $this->externalIds[$placeId] ?? '';
        $this->validate([
            'externalIds.'.$placeId => ['required', 'string', 'max:2000', 'url'],
        ]);

        if ($this->integration->platform?->slug === 'airbnb') {
            if ($this->isAirbnbDetailsUrl($value)) {
                $this->addError('externalIds.'.$placeId, 'Use a URL de exportacao iCal (.ics), nao o link de detalhes da reserva.');

                return;
            }

            $path = parse_url($value, PHP_URL_PATH) ?? '';
            if (! str_ends_with($path, '.ics')) {
                $this->addError('externalIds.'.$placeId, 'A URL do Airbnb deve terminar com .ics.');

                return;
            }
        }

        $this->integration->places()->updateExistingPivot($placeId, [
            'external_id' => $value,
        ]);

        $this->integration->touch();
        session()->flash('status', 'Integração atualizada com sucesso.');
    }

    public function removePlace(int $placeId): void
    {
        $this->integration->places()->detach($placeId);
        unset($this->externalIds[$placeId]);
        $this->integration->refresh();

        if ($this->integration->places->isEmpty()) {
            $this->integration->delete();
            $this->redirectRoute('app.bookings.integrations.index', navigate: true);

            return;
        }

        session()->flash('status', 'Place removido da integração.');
    }

    public function deleteIntegration(): void
    {
        $this->integration->places()->detach();
        $this->integration->delete();

        session()->flash('status', 'Integração removida com sucesso.');
        $this->redirectRoute('app.bookings.integrations.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.integrations.edit')->layout('layouts.client');
    }

    private function isAirbnbDetailsUrl(string $url): bool
    {
        return str_contains($url, '/hosting/reservations/details/');
    }
}
