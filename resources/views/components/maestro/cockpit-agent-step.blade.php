@props([
    'agentType' => '',
    'status' => 'pending',
    'cost' => null,
    'runId' => null,
    'outputExists' => false,
    'attempt' => 1,
    'errorMessage' => null,
    'isLast' => false,
])

@php
$badgeStatus = match ($status) {
    'running' => 'running',
    'completed' => 'completed',
    'blocked' => 'blocked',
    'waiting_gate' => 'gate',
    'skipped' => 'skipped',
    default => 'pending',
};
$connectorColor = match ($status) {
    'completed' => 'var(--maestro-success)',
    'running' => 'var(--maestro-info)',
    default => 'var(--maestro-border-md)',
};
@endphp

<div class="flex gap-4 pb-4">
    <div class="flex flex-col items-center">
        <div class="relative flex h-12 w-12 items-center justify-center rounded-full border-2 bg-maestro-surface-2"
             style="border-color: {{ $connectorColor }}">
            @if($status === 'running')
                <div class="h-3 w-3 animate-pulse rounded-full" style="background: var(--maestro-info)"></div>
            @elseif($status === 'completed')
                <svg class="h-6 w-6" style="color: var(--maestro-success)" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
            @elseif($status === 'blocked')
                <svg class="h-6 w-6" style="color: var(--maestro-danger)" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            @else
                <div class="h-2 w-2 rounded-full bg-maestro-subtle"></div>
            @endif
        </div>

        @if(! $isLast)
            <div class="mt-2 h-8 w-px" style="background: {{ $connectorColor }}"></div>
        @endif
    </div>

    <div class="flex-1 pt-2">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <div class="mb-1 flex items-center gap-2">
                    <x-ui.heading-3 class="capitalize">{{ str_replace('_', ' ', $agentType) }}</x-ui.heading-3>
                    <x-ui.badge :status="$badgeStatus" />
                    @if($attempt > 1)
                        <span class="rounded-full px-2 py-0.5 text-[12px] font-medium" style="background: var(--maestro-warning-bg); color: var(--maestro-warning)">
                            Tentative {{ $attempt }}
                        </span>
                    @endif
                </div>

                <p class="text-[13px] text-maestro-muted">
                    @switch($status)
                        @case('pending') En attente @break
                        @case('running') En cours… @break
                        @case('completed') Terminé @break
                        @case('blocked') Échec @break
                        @case('waiting_gate') Gate en attente @break
                        @case('skipped') Ignoré @break
                    @endswitch
                </p>

                @if($errorMessage)
                    <div class="mt-2 rounded-lg border p-2 text-[12px]" style="border-color: var(--maestro-danger-border); background: var(--maestro-danger-bg); color: var(--maestro-danger)">
                        {{ $errorMessage }}
                    </div>
                @endif
            </div>

            <div class="text-right">
                @if($cost !== null)
                    <div class="rounded-lg border px-3 py-1 text-[13px] font-medium" style="border-color: var(--maestro-warning-border); background: var(--maestro-warning-bg); color: var(--maestro-warning)">
                        ${{ number_format($cost, 4) }}
                    </div>
                @endif

                @if($outputExists && $runId)
                    <button
                        @click="$dispatch('open-output', { runId: {{ $runId }} })"
                        class="mt-2 text-[12px] text-maestro-accent hover:underline"
                    >
                        Voir l'output
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
