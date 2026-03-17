<section>
    <a href="{{ route('app.bookings.integrations.index') }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
    <h1 class="my-2 mb-4">Editar Integração iCal</h1>

    <div class="mb-4 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <p class="m-0 text-neutral-500">
            Plataforma: <strong class="text-neutral-700">{{ $integration->platform?->name ?? 'Plataforma' }}</strong>
        </p>
        <p class="mt-1 text-sm text-neutral-500">
            Use a URL de exportacao iCal (.ics). O sistema grava em UTC e opera em UTC-3 por enquanto.
        </p>
    </div>

    <div class="grid gap-3">
        @forelse ($integration->places as $place)
            <form wire:submit.prevent="updateExternalId({{ $place->id }})" class="grid gap-2.5 rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <div class="flex items-center justify-between">
                    <strong>{{ $place->name }}</strong>
                    <button
                        type="button"
                        onclick="return confirm('Remover este place da integração?')"
                        wire:click="removePlace({{ $place->id }})"
                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700 hover:bg-red-100"
                    >
                        Remover
                    </button>
                </div>

                <div>
                    <label for="externalId-{{ $place->id }}">URL iCal</label><br>
                    <input
                        id="externalId-{{ $place->id }}"
                        type="url"
                        wire:model="externalIds.{{ $place->id }}"
                        class="w-full rounded-md border border-neutral-300 p-2"
                    >
                    @error("externalIds.{$place->id}") <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="cursor-pointer rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700">
                    Atualizar Integração
                </button>
            </form>
        @empty
            <p class="text-neutral-500">Nenhum place associado.</p>
        @endforelse
    </div>

    <div class="mt-4">
        <button
            type="button"
            onclick="return confirm('Remover esta integração?')"
            wire:click="deleteIntegration"
            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-red-700 hover:bg-red-100"
        >
            Remover Integração
        </button>
    </div>
</section>
