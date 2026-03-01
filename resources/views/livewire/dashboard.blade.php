<section>
    <h1 class="m-0 mb-4">Dashboard</h1>

    <div class="grid gap-3">
        @forelse ($places as $place)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <h2 class="mb-2 text-lg">
                    <a href="{{ route('app.places.show', $place->id) }}" class="text-neutral-900 no-underline hover:text-neutral-700">
                        {{ $place->name }}
                    </a>
                </h2>
                <p class="m-0 text-neutral-500">
                    Dispositivos online: {{ $onlineCountByPlace[$place->id] ?? 0 }} / {{ $place->devices_count }}
                </p>
                <p class="mt-1 m-0 text-neutral-500">
                    Próximo check-in:
                    {{ optional($nextCheckInByPlace[$place->id] ?? null)->check_in?->format('d/m/Y H:i') ?? 'Sem reservas futuras' }}
                </p>
            </article>
        @empty
            <p class="text-neutral-500">Nenhum place encontrado.</p>
        @endforelse
    </div>
</section>
