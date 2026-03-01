@php
    $selectId = $attributes->get('id', 'placeId');
@endphp
<div>
    <label for="{{ $selectId }}" class="mb-2 block font-semibold">{{ $label }}</label>
    <select
        id="{{ $selectId }}"
        @if($required) required @endif
        {{ $attributes->merge(['class' => 'max-w-[360px] w-full rounded-lg border border-neutral-300 p-2'])->except('id') }}
    >
        @if ($includeEmpty)
            <option value="">{{ $emptyOptionLabel }}</option>
        @endif
        @foreach ($places as $place)
            <option value="{{ $place->id }}">{{ $place->name }}</option>
        @endforeach
    </select>
    @error($errorName ?? 'placeId')
        <p class="mt-1 text-error-500">{{ $message }}</p>
    @enderror
</div>
