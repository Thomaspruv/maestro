@extends('layouts.maestro')

@section('title', $task->title)

@section('content')
    <div class="mb-4 flex items-center gap-3">
        <a href="{{ route('projects.show', $project) }}" class="text-xs text-text-muted hover:text-primary-light">← {{ $project->name }}</a>
        <x-maestro.badge kind="task_type" :value="$task->type" />
        <x-maestro.badge kind="task_status" :value="$task->status" />
        <x-maestro.badge kind="mode" :value="$task->mode" />
        <x-maestro.badge kind="priority" :value="$task->priority" />
    </div>

    <div class="grid grid-cols-[280px_1fr] gap-4" style="height: calc(100vh - 140px);">
        <div class="overflow-y-auto">
            @livewire('task-pipeline', ['task' => $task])
        </div>
        <div class="min-h-0">
            @livewire('agent-output-viewer', ['task' => $task])
        </div>
    </div>
@endsection
