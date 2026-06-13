@props([
    'project',
    'size' => 'default',
])

@php
    $sizeClasses = match ($size) {
        'compact' => 'maestro-btn-discovery-compact px-3 py-1.5',
        'full' => 'maestro-btn-discovery-full w-full px-4 py-3',
        'banner' => 'maestro-btn-discovery-banner px-4 py-2.5',
        default => 'px-3.5 py-2',
    };
@endphp

<a
    href="{{ route('projects.discovery', $project) }}"
    {{ $attributes->merge(['class' => "maestro-btn-discovery {$sizeClasses}"]) }}
>
    <span class="discovery-btn-icon flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary/20 text-base">
        🤖
    </span>
    <span class="text-left leading-tight">
        <span class="block text-xs font-semibold text-text-primary">Discovery IA</span>
        @if(! in_array($size, ['compact'], true))
            <span class="mt-0.5 block text-[10px] font-normal text-text-muted">
                {{ $size === 'banner' ? 'Chat produit · veille marché · propositions de features' : 'Analyser & proposer des tâches' }}
            </span>
        @endif
    </span>
    @if($size === 'banner')
        <span class="ml-auto text-[10px] font-semibold text-primary-light">Ouvrir le chat →</span>
    @endif
</a>
