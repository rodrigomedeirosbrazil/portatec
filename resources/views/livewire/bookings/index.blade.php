<section>
    <x-page-header title="Reservas" :action-url="route('app.bookings.create')" action-label="Nova Reserva" />

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
                <x-search-input
                    wire:model.live.debounce.300ms="guest"
                    placeholder="Buscar por nome"
                    label="Hóspede"
                    id="guest"
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

    <div class="relative grid gap-3 md:grid-cols-2">
        <x-loading-overlay />

        @forelse ($bookings as $booking)
            @php
                $nights = (int) $booking->check_in->startOfDay()->diffInDays($booking->check_out->startOfDay());
            @endphp
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <div class="flex items-start justify-between">
                    <h2 class="mb-2 text-lg">
                        <a
                            href="{{ route('app.bookings.show', $booking->id) }}"
                            class="min-h-[44px] inline-flex items-center text-neutral-900 no-underline hover:text-neutral-700"
                        >
                            {{ $booking->guest_name ?: 'Sem nome' }}
                        </a>
                    </h2>
                    @if($booking->source !== 'manual')
                        <span class="rounded-full border border-neutral-300 bg-neutral-100 px-2 py-0.5 text-xs text-neutral-500">iCal</span>
                    @endif
                </div>
                @if(! $placeId)
                    <p class="mt-0 mb-1 text-sm font-medium text-neutral-700">{{ $booking->place?->name }}</p>
                @endif
                <p class="m-0 text-neutral-500">
                    {{ $booking->check_in->format('d/m/Y H:i') }} até {{ $booking->check_out->format('d/m/Y H:i') }}
                    <span class="text-neutral-400">({{ $nights }} {{ $nights === 1 ? 'noite' : 'noites' }})</span>
                </p>
            </article>
        @empty
            <x-empty-state
                message="Nenhuma reserva encontrada."
                :action-url="route('app.bookings.create')"
                action-label="Nova Reserva"
            />
        @endforelse
    </div>

    @if($bookings->hasPages())
        <div class="mt-4 flex flex-wrap gap-1">
            {{ $bookings->links() }}
        </div>
    @endif
</section>
