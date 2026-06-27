@props(['variant' => 'secondary', 'size' => 'md', 'type' => 'button', 'tag' => 'button'])

@php
$base = 'inline-flex items-center gap-2 font-medium rounded-lg transition-colors cursor-pointer';
$sizes = ['sm' => 'px-3 py-1.5 text-[12px]', 'md' => 'px-4 py-2 text-[13px]'];
$variants = [
    'primary' => 'bg-maestro-accent text-white hover:bg-maestro-accent-hover border-0',
    'secondary' => 'bg-transparent text-maestro-muted border border-md hover:bg-maestro-surface',
    'danger' => 'bg-transparent text-[var(--maestro-danger)] border border-[var(--maestro-danger-border)] hover:bg-[var(--maestro-danger-bg)]',
    'ghost' => 'bg-transparent text-maestro-accent border-0 hover:bg-maestro-accent/10',
];
$classes = "$base {$sizes[$size]} {$variants[$variant]}";
@endphp

@if ($tag === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
