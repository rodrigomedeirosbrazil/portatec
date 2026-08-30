<section>
    <x-page-header
        title="Integrações de reservas (iCal)"
        :action-url="route('app.bookings.integrations.create')"
        action-label="Nova Integração iCal"
    />

    <div class="mb-4 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <x-place-select
            :places="$places"
            wire:model.live="placeId"
            label="Filtrar por local"
            :include-empty="true"
            empty-option-label="Todos"
            id="place-filter"
        />
    </div>

    <div class="relative grid gap-3">
        <x-loading-overlay />

        @forelse ($integrations as $integration)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-lg">
                        {{ $integration->platform?->name ?? 'Plataforma' }}
                    </h2>
                    <div class="flex gap-2">
                        <a href="{{ route('app.bookings.integrations.edit', $integration->id) }}" class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm text-neutral-700 no-underline hover:bg-neutral-50">
                            Editar
                        </a>
                        <button
                            type="button"
                            wire:confirm="Remover esta integração?"
                            wire:click="deleteIntegration({{ $integration->id }})"
                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700 hover:bg-red-100"
                        >
                            Remover
                        </button>
                    </div>
                </div>
                <p class="m-0 text-neutral-500">
                    Locais: {{ $integration->places->pluck('name')->join(', ') ?: 'Nenhum' }}
                </p>
                <p class="mt-1 m-0 text-neutral-500">
                    Última atualização: {{ $integration->updated_at?->format('d/m/Y H:i') }}
                </p>
            </article>
        @empty
            <x-empty-state
                message="Nenhuma integração encontrada."
                :action-url="route('app.bookings.integrations.create')"
                action-label="Nova Integração iCal"
            />
        @endforelse
    </div>
</section>
