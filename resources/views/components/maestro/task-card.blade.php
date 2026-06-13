@props(['task', 'project' => null, 'health' => null])

@php
    use App\Enums\PipelineHealthState;
    use App\Enums\TaskStatus;
    use App\Support\PipelineActivity;

    $project = $project ?? $task->project;
    $agentLabels = config('maestro.agent_labels', []);
    $currentAgent = PipelineActivity::currentAgentType($task);
    $runningRun = PipelineActivity::runningRun($task);
    $pendingRun = PipelineActivity::pendingRun($task);
    $pendingGate = $task->relationLoaded('gates')
        ? $task->gates->where('status', 'pending')->first()
        : null;
    $agentEmoji = $currentAgent ? ($agentLabels[$currentAgent]['emoji'] ?? '🤖') : null;
    $healthState = $health['state'] ?? null;
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

    @if($health && $task->status === TaskStatus::InProgress)
        @php
            $stripClass = match ($health['tone'] ?? 'muted') {
                'danger' => 'border-danger/30 bg-danger/10 text-danger',
                'warning' => 'border-warning/30 bg-warning-muted/20 text-warning',
                'success' => 'border-success/30 bg-success/10 text-success',
                default => 'border-primary/25 bg-primary-muted/15 text-primary-light',
            };
        @endphp
        <div class="mb-2 rounded-md border px-2 py-1.5 {{ $stripClass }}">
            @if($healthState === PipelineHealthState::BlockedWorker)
                <p class="text-[10px] font-semibold">Bloquée — démarrer Horizon</p>
            @elseif($runningRun && $currentAgent)
                <div class="flex items-start gap-1.5">
                    <span class="pipeline-spinner mt-0.5 h-3 w-3 shrink-0" aria-hidden="true"></span>
                    <p class="text-[10px] leading-snug">{{ $health['title'] }}</p>
                </div>
            @elseif($pendingRun)
                <p class="text-[10px] leading-snug">{{ $health['title'] }} — en file</p>
            @elseif($pendingGate)
                <p class="text-[10px] font-semibold">Validation requise</p>
            @else
                <p class="text-[10px] leading-snug">{{ $health['title'] }}</p>
            @endif
        </div>
    @endif

    @if(isset($task->agentRuns) && $task->agentRuns->isNotEmpty())
        <div class="flex flex-wrap gap-1">
            @foreach($task->agentRuns->sortBy('id') as $run)
                @php
                    $label = config("maestro.agent_labels.{$run->agent_type}.emoji", '🤖');
                    $pillStatus = match ($run->status->value) {
                        'pending', 'running' => 'running',
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
