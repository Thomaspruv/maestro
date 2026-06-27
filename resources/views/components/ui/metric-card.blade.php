@props(['label', 'value', 'sub' => null, 'subColor' => 'success'])

@php
$subColors = [
    'success' => 'var(--maestro-success)',
    'info' => 'var(--maestro-info)',
    'warning' => 'var(--maestro-warning)',
    'danger' => 'var(--maestro-danger)',
];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg bg-maestro-surface p-4']) }}>
    <p class="mb-1 text-[11px] font-medium uppercase tracking-wider text-maestro-subtle">{{ $label }}</p>
    <p class="text-[24px] font-medium text-maestro-text">{{ $value }}</p>
    @if ($sub)
        <p class="mt-0.5 text-[12px]" style="color: {{ $subColors[$subColor] ?? $subColors['success'] }}">{{ $sub }}</p>
    @endif
</div>
