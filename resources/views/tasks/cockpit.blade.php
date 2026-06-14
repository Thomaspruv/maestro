@extends('layouts.maestro')

@section('title', $task->title . ' - Cockpit')

@section('content')
    <div class="mb-4">
        <div class="mb-3 flex items-center gap-3">
            <a href="{{ route('projects.show', $project) }}" class="text-xs text-text-muted hover:text-primary-light">← {{ $project->name }}</a>
            <x-maestro.badge kind="task_type" :value="$task->type" />
            <x-maestro.badge kind="task_status" :value="$task->status" />
            <x-maestro.badge kind="mode" :value="$task->mode" />
            <x-maestro.badge kind="priority" :value="$task->priority" />
        </div>
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-sm font-bold text-text-primary">{{ $task->title }}</h1>
                @if($task->description)
                    <p class="mt-1 max-w-3xl text-[11px] leading-relaxed text-text-muted">{{ $task->description }}</p>
                @endif
            </div>
            <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="text-xs text-text-muted hover:text-primary-light">← Back to Details</a>
        </div>
    </div>

    <div class="h-[calc(100vh-11rem)] overflow-y-auto">
        @livewire('pipeline-cockpit', ['task' => $task])
    </div>
@endsection
