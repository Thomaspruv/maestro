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
        <x-ui.heading-3>Pipeline</x-ui.heading-3>
        <div class="flex items-center gap-2">
            @if($task->status->value !== 'backlog')
                <a href="{{ route('projects.tasks.cockpit', [$task->project_id, $task->uuid]) }}"
                   class="text-[12px] text-maestro-subtle hover:text-maestro-accent"
                   title="Afficher le cockpit temps réel">
                    📊
                </a>
            @endif
            @if($task->status->value === 'backlog')
                <x-ui.button variant="primary" size="sm" wire:click="startPipeline">Démarrer</x-ui.button>
            @endif
        </div>
    </div>

    <x-maestro.pipeline-health-banner :health="$health" />

    @if($shouldPoll)
        <p class="mb-3 text-[12px] text-maestro-subtle">Actualisation automatique toutes les 5 s.</p>
    @endif

    <div class="relative space-y-0">
        @foreach($pipeline as $index => $agent)
            @php
                $run = $runsByAgent[$agent] ?? null;
                $label = $agentLabels[$agent] ?? ['emoji' => '🤖', 'name' => $agent];
                $isCurrent = $currentAgent === $agent;
                $badgeStatus = match ($run?->status->value ?? null) {
                    'pending', 'running' => 'running',
                    'completed' => 'completed',
                    'waiting_gate' => 'gate',
                    'failed' => 'blocked',
                    'skipped' => 'skipped',
                    default => $isCurrent ? 'running' : 'pending',
                };
                $duration = $run ? \App\Support\PipelineActivity::formatDuration($run) : null;
            @endphp

            <div
                @if($run) wire:click="selectRun({{ $run->id }})" @endif
                @class([
                    'relative flex items-start gap-3 rounded-lg border px-3 py-2 transition-colors',
                    'cursor-pointer border-maestro-accent bg-maestro-accent/10' => $run && $selectedRunId === $run->id,
                    'cursor-pointer border hover:border-maestro-accent/30' => $run && $selectedRunId !== $run->id,
                    'border opacity-60' => ! $run && ! $isCurrent,
                    'border-maestro-accent/40 bg-maestro-accent/5 ring-1 ring-maestro-accent/30' => $isCurrent && ! $run,
                    'ring-1 ring-maestro-accent/40' => $isCurrent && $run,
                ])
            >
                @if($index < count($pipeline) - 1)
                    <div class="absolute left-[22px] top-10 h-full w-px bg-white/[0.07]"></div>
                @endif

                <div @class([
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-maestro-surface-2 text-sm',
                    'pipeline-agent-pulse' => in_array($run?->status->value ?? '', ['pending', 'running'], true) || ($isCurrent && ! $run),
                ])>
                    {{ $label['emoji'] }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[13px] font-medium text-maestro-text">{{ $label['name'] }}</p>
                        @if($run)
                            <x-ui.badge :status="$badgeStatus" />
                        @elseif($isCurrent)
                            <x-ui.badge status="running">Prochain</x-ui.badge>
                        @else
                            <x-ui.badge status="pending">En attente</x-ui.badge>
                        @endif
                    </div>

                    @if($run)
                        <div class="mt-1 space-y-0.5 text-[12px] text-maestro-subtle">
                            @if($run->model)
                                <p>{{ $run->model }}</p>
                            @endif
                            @if($duration)
                                <p>Durée : {{ $duration }}</p>
                            @endif
                            @if($run->cost)
                                <p class="text-maestro-muted">${{ number_format($run->cost, 4) }}</p>
                            @endif
                            @if($run->status->value === 'failed' && $run->error_message)
                                <p style="color: var(--maestro-danger)">{{ Str::limit($run->error_message, 120) }}</p>
                            @endif
                        </div>
                    @elseif($isCurrent)
                        <p class="mt-1 text-[12px] text-maestro-accent">Étape courante</p>
                    @endif
                </div>
            </div>

            @php
                $agentGate = $pendingGates->first(fn ($g) => $g->agentRun?->agent_type === $agent);
            @endphp
            @if($agentGate)
                <div class="maestro-gate-block mx-3 my-1" wire:click.stop>
                    <p class="text-[12px] font-medium" style="color: var(--maestro-warning)">Gate en attente — {{ $agentGate->gate_type->value }}</p>
                    <div class="mt-2 flex gap-2">
                        <x-ui.button variant="primary" size="sm" wire:click.stop="approveGate({{ $agentGate->id }})" wire:loading.attr="disabled" wire:target="approveGate">
                            ✓ Approuver
                        </x-ui.button>
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    @if($task->actual_cost > 0)
        <div class="mt-4 rounded-lg border bg-maestro-surface px-3 py-2 text-[12px] text-maestro-subtle">
            Coût cumulé : <span class="font-medium" style="color: var(--maestro-warning)">${{ number_format($task->actual_cost, 4) }}</span>
        </div>
    @endif
</div>
