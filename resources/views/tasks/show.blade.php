@extends('layouts.maestro')

@section('title', $task->title)

@section('content')
    <div class="mb-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.show', $project) }}" class="text-xs text-text-muted hover:text-primary-light">← {{ $project->name }}</a>
                <x-maestro.badge kind="task_type" :value="$task->type" />
                <x-maestro.badge kind="task_status" :value="$task->status" />
                <x-maestro.badge kind="mode" :value="$task->mode" />
                <x-maestro.badge kind="priority" :value="$task->priority" />
            </div>
            @if($task->status->value !== 'backlog')
                <a href="{{ route('projects.tasks.cockpit', [$project, $task]) }}" class="text-xs text-primary-light hover:text-primary underline">
                    View Cockpit →
                </a>
            @endif
        </div>
        <h1 class="text-sm font-bold text-text-primary">{{ $task->title }}</h1>
        @if($task->description)
            <p class="mt-1 max-w-3xl text-[11px] leading-relaxed text-text-muted">{{ $task->description }}</p>
        @endif
    </div>

    <div class="grid h-[calc(100vh-11rem)] grid-cols-[minmax(240px,280px)_1fr] gap-4">
        <div class="h-full overflow-y-auto">
            @livewire('task-pipeline', ['task' => $task])
        </div>
        <div class="flex h-full min-h-0 flex-col">
            @livewire('agent-output-viewer', ['task' => $task])
        </div>
    </div>
@endsection
