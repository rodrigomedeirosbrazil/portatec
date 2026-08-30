@props([
    'title',
    'actionUrl' => null,
    'actionLabel' => null,
])

<div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="m-0">{{ $title }}</h1>
    @if ($actionUrl && $actionLabel)
        <a
            href="{{ $actionUrl }}"
            class="min-h-[44px] min-w-[44px] rounded-lg bg-primary-500 px-3 py-2 text-white no-underline hover:bg-primary-700 sm:inline-flex sm:items-center sm:justify-center"
        >
            {{ $actionLabel }}
        </a>
    @endif
</div>
