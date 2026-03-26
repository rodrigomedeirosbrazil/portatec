<section>
    <a href="{{ route('app.places.show', $place->id) }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar ao local</a>
    <h1 class="my-2 mb-4">Adicionar dispositivo ao local</h1>
    <p class="mb-4 text-neutral-600">Escolha um dispositivo existente ou que esteja em outro local que você acessa para associá-lo a &quot;{{ $place->name }}&quot;.</p>

    <form wire:submit="attach" class="grid gap-2.5 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <div>
            <label for="deviceId">Dispositivo</label><br>
            <select id="deviceId" wire:model="deviceId" class="w-full rounded-lg border border-neutral-300 p-2" required>
                <option value="">Selecione um dispositivo</option>
                @foreach ($devices as $device)
                    <option value="{{ $device->id }}">
                        {{ $device->name }}
                        ({{ $device->brand->value }})
                        — {{ $device->places->pluck('name')->join(', ') ?: ($device->place?->name ?? 'Sem local') }}
                    </option>
                @endforeach
            </select>
            @error('deviceId') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="cursor-pointer rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700">
            Associar ao local
        </button>
    </form>

    @if ($devices->isEmpty())
        <p class="mt-4 text-neutral-500">Não há dispositivos disponíveis para associar. Crie um novo dispositivo em Dispositivos ou use um que já esteja em outro local que você acessa.</p>
    @endif
</section>
