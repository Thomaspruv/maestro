@props(['health'])

@php
    $toneClass = match ($health['tone'] ?? 'muted') {
        'danger' => 'pipeline-health-danger',
        'warning' => 'pipeline-health-warning',
        'success' => 'pipeline-health-success',
        'primary' => 'pipeline-health-primary',
        default => 'pipeline-health-muted',
    };
    $showSpinner = in_array($health['state']->value ?? '', ['queued', 'running'], true);
@endphp

<div {{ $attributes->merge(['class' => "pipeline-health-banner mb-4 rounded-lg border px-3 py-3 {$toneClass}"]) }}>
    <div class="flex items-start gap-2">
        @if($showSpinner)
            <span class="pipeline-spinner mt-0.5 shrink-0" aria-hidden="true"></span>
        @elseif(($health['tone'] ?? '') === 'danger')
            <span class="mt-0.5 shrink-0 text-sm" aria-hidden="true">⚠</span>
        @endif
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold">{{ $health['title'] }}</p>
            <p class="mt-0.5 text-[10px] leading-relaxed opacity-90">{{ $health['message'] }}</p>

            @if(($health['total_steps'] ?? 0) > 0)
                <p class="mt-1.5 text-[10px] opacity-80">
                    Étape {{ $health['current_step'] }}/{{ $health['total_steps'] }}
                    · {{ $health['completed_count'] }}/{{ $health['total_steps'] }} agents terminés
                </p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/20">
                    <div
                        class="pipeline-health-progress h-full rounded-full transition-all duration-500"
                        style="width: {{ max(2, $health['progress']) }}%"
                    ></div>
                </div>
            @endif

            @if(($health['state']->value ?? '') === 'blocked_worker')
                <div class="mt-2 flex flex-wrap gap-2">
                    <a href="{{ url('/horizon') }}" target="_blank" class="maestro-btn-ghost px-2 py-1 text-[10px]">
                        Ouvrir Horizon →
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
