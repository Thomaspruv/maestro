<div
    x-data
    x-init="
        if (window.Echo) {
            Echo.channel('task.{{ $task->id }}')
                .listen('.AgentRunUpdated', () => $wire.refreshTask())
                .listen('.GatePending', () => $wire.refreshTask());
        }
    "
>
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-xs font-semibold text-text-primary">Pipeline</h2>
        @if($task->status->value === 'backlog')
            <x-maestro.button wire:click="startPipeline" class="text-[10px]">Démarrer</x-maestro.button>
        @endif
    </div>

    <div class="relative space-y-0">
        @foreach($pipeline as $index => $agent)
            @php
                $run = $runsByAgent[$agent] ?? null;
                $label = $agentLabels[$agent] ?? ['emoji' => '🤖', 'name' => $agent];
                $isActive = $task->current_agent?->value === $agent;
                $pillStatus = match ($run?->status->value ?? null) {
                    'running' => 'running',
                    'completed' => 'done',
                    'waiting_gate' => 'gate',
                    'failed' => 'error',
                    default => $isActive ? 'running' : 'waiting',
                };
            @endphp

            <div
                @if($run) wire:click="selectRun({{ $run->id }})" @endif
                @class([
                    'relative flex items-start gap-3 rounded-lg border px-3 py-2 transition-colors',
                    'cursor-pointer border-primary bg-primary-muted/30' => $run && $selectedRunId === $run->id,
                    'cursor-pointer border-bg-overlay hover:border-primary/30' => $run && $selectedRunId !== $run->id,
                    'border-bg-overlay opacity-60' => ! $run,
                ])
            >
                @if($index < count($pipeline) - 1)
                    <div class="absolute left-[22px] top-10 h-full w-px bg-bg-overlay"></div>
                @endif

                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-bg-elevated text-sm">
                    {{ $label['emoji'] }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-text-primary">{{ $label['name'] }}</p>
                        <x-maestro.pipeline-pill :status="$pillStatus">
                            @if($run)
                                <x-maestro.badge kind="agent_status" :value="$run->status" />
                            @else
                                En attente
                            @endif
                        </x-maestro.pipeline-pill>
                    </div>
                    @if($run?->cost)
                        <p class="mt-0.5 text-[10px] text-text-muted">${{ number_format($run->cost, 4) }}</p>
                    @endif
                </div>
            </div>

            @php
                $agentGate = $pendingGates->first(fn ($g) => $g->agentRun?->agent_type === $agent);
            @endphp
            @if($agentGate)
                <div class="maestro-gate-block mx-3 my-1">
                    <p class="text-[10px] font-semibold text-warning">🚧 Gate en attente — {{ $agentGate->gate_type->value }}</p>
                </div>
            @endif
        @endforeach
    </div>
</div>
