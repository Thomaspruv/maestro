@props(['task', 'project' => null])

@php
    use App\Enums\TaskStatus;
    use App\Support\PipelineActivity;

    $project = $project ?? $task->project;
    $agentLabels = config('maestro.agent_labels', []);
    $currentAgent = PipelineActivity::currentAgentType($task);
    $runningRun = PipelineActivity::runningRun($task);
    $pendingGate = $task->relationLoaded('gates')
        ? $task->gates->where('status', 'pending')->first()
        : null;
    $agentEmoji = $currentAgent ? ($agentLabels[$currentAgent]['emoji'] ?? '🤖') : null;
@endphp

<div {{ $attributes->merge(['class' => 'maestro-card p-3']) }}>
    <div class="mb-2 flex items-start justify-between gap-2">
        <p class="text-xs font-semibold text-text-primary line-clamp-2">{{ $task->title }}</p>
        <x-maestro.badge kind="priority" :value="$task->priority" />
    </div>

    @if($task->module)
        <p class="mb-2 text-[10px] text-text-muted">{{ $task->module }}</p>
    @endif

    <div class="mb-2 flex flex-wrap gap-1">
        <x-maestro.badge kind="task_type" :value="$task->type" />
        <x-maestro.badge kind="mode" :value="$task->mode" />
    </div>

    @if($task->status === TaskStatus::InProgress)
        <div class="mb-2 rounded-md border border-primary/25 bg-primary-muted/15 px-2 py-1.5">
            @if($runningRun && $currentAgent)
                <div class="flex items-start gap-1.5">
                    <span class="pipeline-spinner mt-0.5 h-3 w-3 shrink-0" aria-hidden="true"></span>
                    <p class="text-[10px] leading-snug text-primary-light">
                        {{ $agentLabels[$currentAgent]['name'] ?? $currentAgent }}
                        — {{ PipelineActivity::agentMessage($currentAgent) }}
                    </p>
                </div>
            @elseif($pendingGate)
                <p class="text-[10px] font-semibold text-warning">🚧 Validation requise — cliquez pour approuver</p>
            @else
                <p class="text-[10px] text-text-muted">⏳ Pipeline en cours…</p>
            @endif
        </div>
    @endif

    @if(isset($task->agentRuns) && $task->agentRuns->isNotEmpty())
        <div class="flex flex-wrap gap-1">
            @foreach($task->agentRuns->sortBy('id') as $run)
                @php
                    $label = config("maestro.agent_labels.{$run->agent_type}.emoji", '🤖');
                    $pillStatus = match ($run->status->value) {
                        'running' => 'running',
                        'completed' => 'done',
                        'waiting_gate' => 'gate',
                        'failed' => 'error',
                        default => 'waiting',
                    };
                @endphp
                <x-maestro.pipeline-pill :status="$pillStatus" :label="$label" />
            @endforeach
        </div>
    @elseif($agentEmoji)
        <x-maestro.pipeline-pill status="running" :label="$agentEmoji" />
    @endif

    <div class="mt-2 flex items-center justify-between text-[10px] text-text-muted">
        @if($task->estimated_cost)
            <span>~{{ number_format($task->estimated_cost, 2) }} $</span>
        @else
            <span></span>
        @endif
        @if($task->actual_cost)
            <span class="text-warning">{{ number_format($task->actual_cost, 2) }} $ réel</span>
        @endif
    </div>
</div>
