<section>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="m-0">Integrações</h1>
        <a href="{{ route('app.integrations.create') }}" class="rounded-lg bg-primary-500 px-3 py-2 text-white no-underline hover:bg-primary-700">
            Nova Integração
        </a>
    </div>

    <div class="grid gap-3">
        @forelse ($integrations as $integration)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <h2 class="mb-2 text-lg">
                    {{ $integration->platform?->name ?? 'Plataforma' }}
                </h2>
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
