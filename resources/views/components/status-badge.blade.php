@props([
    'status',
    'size' => 'sm',
])

@php
    $colors = match ($status) {
        'active', 'online', 'current' => 'border-success-300 bg-success-100 text-success-700',
        'expired', 'offline', 'past' => 'border-neutral-300 bg-neutral-100 text-neutral-500',
        'future' => 'border-primary-300 bg-primary-100 text-primary-700',
        default => 'border-neutral-300 bg-neutral-100 text-neutral-500',
    };

    $label = match ($status) {
        'active' => 'Ativo',
        'expired' => 'Expirado',
        'future' => 'Futuro',
        'online' => 'Online',
        'offline' => 'Offline',
        'current' => 'Em andamento',
        'past' => 'Concluída',
        default => $status,
    };

    $sizeClasses = match ($size) {
        'md' => 'px-2.5 py-1 text-sm',
        default => 'px-2 py-0.5 text-xs',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border font-medium {$colors} {$sizeClasses}"]) }}>
    {{ $label }}
</span>
