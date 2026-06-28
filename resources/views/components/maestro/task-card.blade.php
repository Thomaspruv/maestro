@props(['task', 'project' => null, 'health' => null])

@php
    use App\Enums\PipelineHealthState;
    use App\Enums\TaskStatus;
    use App\Support\PipelineActivity;

    $project = $project ?? $task->project;
    $agentLabels = config('maestro.role_labels', []);
    $currentAgent = PipelineActivity::currentPipelineRoleSlug($task);
    $runningRun = PipelineActivity::runningRun($task);
    $pendingRun = PipelineActivity::pendingRun($task);
    $pendingGate = $task->relationLoaded('gates')
        ? $task->gates->where('status', 'pending')->first()
        : null;
    $agentEmoji = $currentAgent ? ($agentLabels[$currentAgent]['emoji'] ?? '🤖') : null;
    $healthState = $health['state'] ?? null;
@endphp

<x-ui.card {{ $attributes->merge(['class' => 'p-3']) }}>
    <div class="mb-2 flex items-start justify-between gap-2">
        <p class="line-clamp-2 text-[13px] font-medium text-maestro-text">{{ $task->title }}</p>
        <x-maestro.badge kind="priority" :value="$task->priority" />
    </div>

    @if($task->module)
        <p class="mb-2 text-[12px] text-maestro-subtle">{{ $task->module }}</p>
    @endif

    <div class="mb-2 flex flex-wrap gap-1">
        <x-maestro.badge kind="task_type" :value="$task->type" />
        <x-maestro.badge kind="mode" :value="$task->mode" />
    </div>

    @if($health && in_array($task->status, [TaskStatus::InProgress, TaskStatus::WaitingHermes], true))
        @php
            $stripClass = match ($health['tone'] ?? 'muted') {
                'danger' => 'border-[var(--maestro-danger-border)] bg-[var(--maestro-danger-bg)] text-[var(--maestro-danger)]',
                'warning' => 'border-[var(--maestro-warning-border)] bg-[var(--maestro-warning-bg)] text-[var(--maestro-warning)]',
                'success' => 'border-[var(--maestro-success-border)] bg-[var(--maestro-success-bg)] text-[var(--maestro-success)]',
                default => 'border-maestro-accent/25 bg-maestro-accent/10 text-maestro-accent',
            };
        @endphp
        <div class="mb-2 rounded-lg border px-2 py-1.5 {{ $stripClass }}">
            @if($healthState === PipelineHealthState::BlockedWorker)
                <p class="text-[12px] font-medium">Bloquée — démarrer Horizon</p>
            @elseif($healthState === PipelineHealthState::WaitingHermes)
                <p class="text-[12px] leading-snug">{{ $health['title'] }}</p>
            @elseif($runningRun && $currentAgent)
                <div class="flex items-start gap-1.5">
                    <span class="pipeline-spinner mt-0.5 h-3 w-3 shrink-0" aria-hidden="true"></span>
                    <p class="text-[12px] leading-snug">{{ $health['title'] }}</p>
                </div>
            @elseif($pendingRun)
                <p class="text-[12px] leading-snug">{{ $health['title'] }} — en file</p>
            @elseif($pendingGate)
                <p class="text-[12px] font-medium">Validation requise</p>
            @else
                <p class="text-[12px] leading-snug">{{ $health['title'] }}</p>
            @endif
        </div>
    @endif

    @if(isset($task->pipelineSteps) && $task->pipelineSteps->isNotEmpty())
        <div class="flex flex-wrap gap-1">
            @foreach($task->pipelineSteps->sortBy('id') as $run)
                @php
                    $label = config("maestro.role_labels.{$run->role}.emoji", '🤖');
                    $pillStatus = match ($run->status->value) {
                        'pending', 'running' => 'running',
                        'completed' => 'completed',
                        'waiting_gate' => 'gate',
                        'failed' => 'blocked',
                        default => 'pending',
                    };
                @endphp
                <x-ui.badge :status="$pillStatus">{{ $label }}</x-ui.badge>
            @endforeach
        </div>
    @elseif($agentEmoji)
        <x-ui.badge status="running">{{ $agentEmoji }}</x-ui.badge>
    @endif

    <div class="mt-2 flex items-center justify-between text-[12px] text-maestro-subtle">
        @if($task->estimated_cost)
            <span>~{{ number_format($task->estimated_cost, 2) }} $</span>
        @else
            <span></span>
        @endif
        @if($task->actual_cost)
            <span style="color: var(--maestro-warning)">{{ number_format($task->actual_cost, 2) }} $ réel</span>
        @endif
    </div>
</x-ui.card>
