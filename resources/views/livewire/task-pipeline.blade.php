<div
    x-data
    x-init="
        if (window.Echo) {
            Echo.channel('task.{{ $task->id }}')
                .listen('.AgentRunUpdated', () => $wire.refreshTask())
                .listen('.GatePending', () => $wire.refreshTask());
        }
    "
    @if($shouldPoll) wire:poll.5s="refreshTask" @endif
>
    <div class="mb-4 flex items-center justify-between gap-2">
        <div>
            <h2 class="text-xs font-semibold text-text-primary">Pipeline</h2>
        </div>
        @if($task->status->value === 'backlog')
            <x-maestro.button wire:click="startPipeline" class="text-[10px]">Démarrer</x-maestro.button>
        @endif
    </div>

    <x-maestro.pipeline-health-banner :health="$health" />

    @if($shouldPoll)
        <p class="mb-3 text-[10px] text-text-muted">Actualisation automatique toutes les 5 s.</p>
    @endif

    <div class="relative space-y-0">
        @foreach($pipeline as $index => $agent)
            @php
                $run = $runsByAgent[$agent] ?? null;
                $label = $agentLabels[$agent] ?? ['emoji' => '🤖', 'name' => $agent];
                $isCurrent = $currentAgent === $agent;
                $pillStatus = match ($run?->status->value ?? null) {
                    'pending' => 'running',
                    'running' => 'running',
                    'completed' => 'done',
                    'waiting_gate' => 'gate',
                    'failed' => 'error',
                    'skipped' => 'done',
                    default => $isCurrent ? 'running' : 'waiting',
                };
                $duration = $run ? \App\Support\PipelineActivity::formatDuration($run) : null;
            @endphp

            <div
                @if($run) wire:click="selectRun({{ $run->id }})" @endif
                @class([
                    'relative flex items-start gap-3 rounded-lg border px-3 py-2 transition-colors',
                    'cursor-pointer border-primary bg-primary-muted/30' => $run && $selectedRunId === $run->id,
                    'cursor-pointer border-bg-overlay hover:border-primary/30' => $run && $selectedRunId !== $run->id,
                    'border-bg-overlay opacity-60' => ! $run && ! $isCurrent,
                    'border-primary/40 bg-primary-muted/10 ring-1 ring-primary/30' => $isCurrent && ! $run,
                    'ring-1 ring-primary/40' => $isCurrent && $run,
                ])
            >
                @if($index < count($pipeline) - 1)
                    <div class="absolute left-[22px] top-10 h-full w-px bg-bg-overlay"></div>
                @endif

                <div @class([
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-bg-elevated text-sm',
                    'pipeline-agent-pulse' => in_array($run?->status->value ?? '', ['pending', 'running'], true) || ($isCurrent && ! $run),
                ])>
                    {{ $label['emoji'] }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-text-primary">{{ $label['name'] }}</p>
                        <x-maestro.pipeline-pill :status="$pillStatus">
                            @if($run)
                                <x-maestro.badge kind="agent_status" :value="$run->status" />
                            @elseif($isCurrent)
                                Prochain
                            @else
                                En attente
                            @endif
                        </x-maestro.pipeline-pill>
                    </div>

                    @if($run)
                        <div class="mt-1 space-y-0.5 text-[10px] text-text-muted">
                            @if($run->model)
                                <p>{{ $run->model }}</p>
                            @endif
                            @if($duration)
                                <p>Durée : {{ $duration }}</p>
                            @endif
                            @if($run->cost)
                                <p class="text-text-secondary">${{ number_format($run->cost, 4) }}</p>
                            @endif
                            @if($run->status->value === 'failed' && $run->error_message)
                                <p class="text-danger">{{ Str::limit($run->error_message, 120) }}</p>
                            @endif
                        </div>
                    @elseif($isCurrent)
                        <p class="mt-1 text-[10px] text-primary-light">Étape courante</p>
                    @endif
                </div>
            </div>

            @php
                $agentGate = $pendingGates->first(fn ($g) => $g->agentRun?->agent_type === $agent);
            @endphp
            @if($agentGate)
                <div class="maestro-gate-block mx-3 my-1">
                    <p class="text-[10px] font-semibold text-warning">Gate en attente — {{ $agentGate->gate_type->value }}</p>
                </div>
            @endif
        @endforeach
    </div>

    @if($task->actual_cost > 0)
        <div class="mt-4 rounded-lg border border-bg-overlay bg-bg-surface/50 px-3 py-2 text-[10px] text-text-muted">
            Coût cumulé : <span class="font-semibold text-warning">${{ number_format($task->actual_cost, 4) }}</span>
        </div>
    @endif
</div>
