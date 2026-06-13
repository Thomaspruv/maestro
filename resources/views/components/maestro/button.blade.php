@props([
    'variant' => 'primary',
    'type' => 'button',
    'tag' => 'button',
])

@php
    $classes = match ($variant) {
        'ghost' => 'maestro-btn-ghost',
        'danger' => 'maestro-btn-danger',
        default => 'maestro-btn-primary',
    };
@endphp

@if ($tag === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
