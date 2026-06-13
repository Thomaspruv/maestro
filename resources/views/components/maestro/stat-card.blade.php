@props(['label', 'value', 'hint' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'maestro-stat-card']) }}>
    <div class="flex items-start justify-between gap-2">
        <p class="maestro-section-title">{{ $label }}</p>
        @if($icon)
            <span class="text-base opacity-60">{{ $icon }}</span>
        @endif
    </div>
    <p class="mt-1 text-xl font-bold text-text-primary">{{ $value }}</p>
    @if($hint)
        <p class="mt-0.5 text-[10px] text-text-muted">{{ $hint }}</p>
    @endif
</div>
