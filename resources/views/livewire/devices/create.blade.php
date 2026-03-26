<section>
    <a href="{{ $placeIds[0] ?? null ? route('app.places.show', $placeIds[0]) : route('app.devices.index') }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
    <h1 class="my-2 mb-4">Novo Dispositivo</h1>

    <form wire:submit="save" class="grid gap-2.5 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <div>
            <label for="placeIds" class="mb-2 block font-semibold">Locais</label>
            <select
                id="placeIds"
                wire:model="placeIds"
                multiple
                class="w-full rounded-lg border border-neutral-300 p-2"
                required
            >
                @foreach ($places as $place)
                    <option value="{{ $place->id }}">{{ $place->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-sm text-neutral-500">Selecione um ou mais locais para este dispositivo.</p>
            @error('placeIds') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
            @error('placeIds.*') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="name">Nome</label><br>
            <input id="name" type="text" wire:model="name" class="w-full rounded-lg border border-neutral-300 p-2" required>
            @error('name') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="brand">Marca</label><br>
            <select id="brand" wire:model="brand" class="w-full rounded-lg border border-neutral-300 p-2" required>
                @foreach ($brands as $brandOption)
                    <option value="{{ $brandOption->value }}">{{ $brandOption->value }}</option>
                @endforeach
            </select>
            @error('brand') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="external_device_id">ID externo (opcional)</label><br>
            <input id="external_device_id" type="text" wire:model="external_device_id" class="w-full rounded-lg border border-neutral-300 p-2">
            @error('external_device_id') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="default_pin">PIN padrão (opcional, 6 dígitos)</label><br>
            <input id="default_pin" type="text" wire:model="default_pin" class="w-full rounded-lg border border-neutral-300 p-2" maxlength="6" inputmode="numeric" pattern="[0-9]*">
            @error('default_pin') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="cursor-pointer rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700">
            Salvar Dispositivo
        </button>
    </form>
</section>
