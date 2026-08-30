<section>
    <a href="{{ route('app.bookings.integrations.index') }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
    <h1 class="my-2 mb-4">Nova Integração iCal</h1>

    <form wire:submit="save" class="grid gap-2.5 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <div>
            <label for="platformId">Plataforma</label><br>
            <select id="platformId" wire:model="platformId" class="w-full rounded-md border border-neutral-300 p-2">
                @foreach ($platforms as $platform)
                    <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="placeId">Place</label><br>
            <select id="placeId" wire:model="placeId" class="w-full rounded-md border border-neutral-300 p-2">
                @foreach ($places as $place)
                    <option value="{{ $place->id }}">{{ $place->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="externalId">URL iCal</label><br>
            <input id="externalId" type="url" wire:model="externalId" class="w-full rounded-md border border-neutral-300 p-2">
            <p class="mt-1 text-sm text-neutral-500">
                Use a URL de exportacao iCal (.ics). O sistema grava em UTC e opera em UTC-3 por enquanto.
            </p>
        </div>

        <button type="submit" class="cursor-pointer rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700">
            Salvar Integração
        </button>
    </form>
</section>
