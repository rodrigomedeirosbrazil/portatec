<section>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="m-0">Integrações</h1>
        <div class="flex gap-2">
            <a href="{{ route('app.integrations.tuya-connect') }}"
               wire:navigate
               class="rounded-lg border border-primary-500 bg-white px-3 py-2
                      text-primary-600 no-underline hover:bg-primary-50">
                Conectar via Tuya
            </a>
            <a href="{{ route('app.integrations.create') }}"
               class="rounded-lg bg-primary-500 px-3 py-2
                      text-white no-underline hover:bg-primary-700">
                Nova Integração
            </a>
        </div>
    </div>

    <div class="grid gap-3">
        @forelse ($integrations as $integration)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-lg">
                        {{ $integration->platform?->name ?? 'Plataforma' }}
                    </h2>
                    <div class="flex gap-2">
                        <a href="{{ route('app.integrations.edit', $integration->id) }}" class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm text-neutral-700 no-underline hover:bg-neutral-50">
                            Editar
                        </a>
                        <button
                            type="button"
                            onclick="return confirm('Remover esta integração?')"
                            wire:click="deleteIntegration({{ $integration->id }})"
                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700 hover:bg-red-100"
                        >
                            Remover
                        </button>
                    </div>
                </div>
                <p class="m-0 text-neutral-500">
                    Places: {{ $integration->places->pluck('name')->join(', ') ?: 'Nenhum' }}
                </p>
                <p class="mt-1 m-0 text-neutral-500">
                    Última atualização: {{ $integration->updated_at?->format('d/m/Y H:i') }}
                </p>
            </article>
        @empty
            <p class="text-neutral-500">Nenhuma integração encontrada.</p>
        @endforelse
    </div>
</section>
