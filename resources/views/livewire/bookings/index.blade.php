<section>
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="m-0">Reservas</h1>
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('app.bookings.integrations.index') }}"
                class="min-h-[44px] rounded-lg border border-primary-500 bg-white px-3 py-2 text-primary-600 no-underline hover:bg-primary-50 sm:inline-flex sm:items-center sm:justify-center"
            >
                Integrações iCal
            </a>
            <a
                href="{{ route('app.bookings.create') }}"
                class="min-h-[44px] min-w-[44px] rounded-lg bg-primary-500 px-3 py-2 text-white no-underline hover:bg-primary-700 sm:inline-flex sm:items-center sm:justify-center"
            >
                Nova Reserva
            </a>
        </div>
    </div>

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
                <label for="date-from" class="mb-2 block font-semibold">Data início</label>
                <input
                    id="date-from"
                    type="date"
                    wire:model.live="dateFrom"
                    class="w-full min-w-0 rounded-lg border border-neutral-300 p-2"
                />
            </div>

            <div class="min-w-0">
                <label for="date-to" class="mb-2 block font-semibold">Data fim</label>
                <input
                    id="date-to"
                    type="date"
                    wire:model.live="dateTo"
                    class="w-full min-w-0 rounded-lg border border-neutral-300 p-2"
                />
            </div>

            <div class="min-w-0">
                <label for="status" class="mb-2 block font-semibold">Status</label>
                <select
                    id="status"
                    wire:model.live="status"
                    class="w-full min-w-0 rounded-lg border border-neutral-300 p-2"
                >
                    <option value="">Todas</option>
                    <option value="future">Futuras</option>
                    <option value="current">Em andamento</option>
                    <option value="past">Concluídas</option>
                </select>
            </div>

            <div class="min-w-0">
                <label for="guest" class="mb-2 block font-semibold">Hóspede</label>
                <input
                    id="guest"
                    type="search"
                    wire:model.live.debounce.300ms="guest"
                    placeholder="Buscar por nome"
                    class="w-full min-w-0 rounded-lg border border-neutral-300 p-2"
                />
            </div>

            <div class="min-w-0">
                <label for="source" class="mb-2 block font-semibold">Origem</label>
                <select
                    id="source"
                    wire:model.live="source"
                    class="w-full min-w-0 rounded-lg border border-neutral-300 p-2"
                >
                    <option value="">Todas</option>
                    <option value="manual">Manual</option>
                    <option value="ical">Integração (iCal)</option>
                </select>
            </div>
        </div>
    </div>

    @if($bookings->total() > 0)
        <p class="mb-3 text-neutral-500">
            Mostrando {{ $bookings->firstItem() }}–{{ $bookings->lastItem() }} de {{ $bookings->total() }} reservas.
        </p>
    @endif

    <div class="grid gap-3 md:grid-cols-2">
        @forelse ($bookings as $booking)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <h2 class="mb-2 text-lg">
                    <a
                        href="{{ route('app.bookings.show', $booking->id) }}"
                        class="min-h-[44px] inline-flex items-center text-neutral-900 no-underline hover:text-neutral-700"
                    >
                        {{ $booking->guest_name ?: 'Sem nome' }}
                    </a>
                </h2>
                <p class="m-0 text-neutral-500">
                    {{ $booking->check_in->format('d/m/Y H:i') }} até {{ $booking->check_out->format('d/m/Y H:i') }}
                </p>
                @if($booking->source !== 'manual')
                    <p class="mt-1 text-xs text-neutral-400">iCal</p>
                @else
                    <p class="mt-1 text-xs text-neutral-400">Manual</p>
                @endif
            </article>
        @empty
            <p class="col-span-full text-neutral-500">Nenhuma reserva encontrada.</p>
        @endforelse
    </div>

    @if($bookings->hasPages())
        <div class="mt-4 flex flex-wrap gap-1">
            {{ $bookings->links() }}
        </div>
    @endif
</section>
