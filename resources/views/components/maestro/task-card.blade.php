@props(['task', 'project' => null])

@php
    $project = $project ?? $task->project;
    $agentLabels = config('maestro.agent_labels', []);
    $currentAgent = $task->current_agent;
    $agentEmoji = $currentAgent ? ($agentLabels[$currentAgent->value]['emoji'] ?? '🤖') : null;
@endphp

<a href="{{ route('projects.tasks.show', [$project, $task]) }}"
   {{ $attributes->merge(['class' => 'maestro-card block cursor-pointer p-3 transition-colors hover:border-primary/40']) }}
   data-task-id="{{ $task->id }}">
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

    @if(isset($task->agentRuns) && $task->agentRuns->isNotEmpty())
        <div class="flex flex-wrap gap-1">
            @foreach($task->agentRuns->sortBy('id') as $run)
                @php
                    $label = config("maestro.agent_labels.{$run->agent_type->value}.emoji", '🤖');
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
</a>
