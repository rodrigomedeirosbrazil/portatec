@props([
    'href',
    'route',
    'mobile' => false,
])

@php
    $isActive = request()->routeIs($route);

    $classes = $mobile
        ? ($isActive
            ? 'py-2 font-semibold text-primary-700 no-underline'
            : 'py-2 text-neutral-700 no-underline hover:text-primary-700')
        : ($isActive
            ? 'font-semibold text-primary-700 no-underline'
            : 'text-neutral-700 no-underline hover:text-primary-700');
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
