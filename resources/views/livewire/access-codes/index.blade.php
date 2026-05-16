<section>
    <x-page-header title="Códigos de Acesso" :action-url="route('app.access-codes.create')" action-label="Novo Código" />

    <div class="mb-4 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="min-w-0">
                <x-place-select
                    :places="$places"
                    wire:model.live="placeId"
                    label="Local"
                    :include-empty="true"
                    empty-option-label="Todos"
                    id="place-filter"
                />
            </div>

            <div class="min-w-0">
                <label for="status" class="mb-2 block font-semibold">Status</label>
                <select
                    id="status"
                    wire:model.live="status"
                    class="w-full min-w-0 rounded-lg border border-neutral-300 p-2"
                >
                    <option value="">Todos</option>
                    <option value="active">Ativos</option>
                    <option value="future">Futuros</option>
                    <option value="expired">Expirados</option>
                </select>
            </div>

            <div class="min-w-0">
                <x-search-input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por PIN..."
                    label="Buscar"
                    id="search"
                />
            </div>
        </div>
    </div>

    @if($accessCodes->total() > 0)
        <p class="mb-3 text-neutral-500">
            Mostrando {{ $accessCodes->firstItem() }}–{{ $accessCodes->lastItem() }} de {{ $accessCodes->total() }} códigos.
        </p>
    @endif

    <div class="relative grid gap-3">
        <x-loading-overlay />

        @forelse ($accessCodes as $accessCode)
            @php
                $codeStatus = match (true) {
                    $accessCode->end !== null && $accessCode->end->lt($now) => 'expired',
                    $accessCode->start->gt($now) => 'future',
                    default => 'active',
                };
            @endphp
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <div class="flex items-start justify-between">
                    <h2 class="mb-2 text-lg">
                        <a href="{{ route('app.access-codes.edit', $accessCode->id) }}" class="text-neutral-900 no-underline hover:text-neutral-700">
                            PIN {{ $accessCode->pin }}
                        </a>
                    </h2>
                    <x-status-badge :status="$codeStatus" />
                </div>
                <p class="m-0 text-neutral-500">{{ $accessCode->display_name }}</p>
                <p class="mt-1 m-0 text-neutral-500">
                    {{ $accessCode->start->format('d/m/Y H:i') }} até {{ $accessCode->end?->format('d/m/Y H:i') ?? 'Sem expiração' }}
                </p>
            </article>
        @empty
            <x-empty-state
                message="Nenhum código de acesso encontrado."
                :action-url="route('app.access-codes.create')"
                action-label="Novo Código"
            />
        @endforelse
    </div>

    @if($accessCodes->hasPages())
        <div class="mt-4 flex flex-wrap gap-1">
            {{ $accessCodes->links() }}
        </div>
    @endif
</section>
