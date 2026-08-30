@props([
    'placeholder' => 'Buscar...',
    'id' => 'search',
    'label' => null,
])

<div>
    @if ($label)
        <label for="{{ $id }}" class="mb-2 block font-semibold">{{ $label }}</label>
    @endif
    <div class="relative">
        <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input
            id="{{ $id }}"
            type="search"
            placeholder="{{ $placeholder }}"
            {{ $attributes->merge(['class' => 'w-full min-w-0 rounded-lg border border-neutral-300 py-2 pl-9 pr-3']) }}
        />
    </div>
</div>
