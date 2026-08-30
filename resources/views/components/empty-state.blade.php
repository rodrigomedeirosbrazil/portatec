@props([
    'message',
    'actionUrl' => null,
    'actionLabel' => null,
])

<div {{ $attributes->merge(['class' => 'col-span-full py-8 text-center']) }}>
    <svg class="mx-auto mb-3 h-12 w-12 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
    </svg>
    <p class="mb-3 text-neutral-500">{{ $message }}</p>
    @if ($actionUrl && $actionLabel)
        <a
            href="{{ $actionUrl }}"
            class="inline-flex items-center rounded-lg bg-primary-500 px-4 py-2 text-white no-underline hover:bg-primary-700"
        >
            {{ $actionLabel }}
        </a>
    @endif
</div>
