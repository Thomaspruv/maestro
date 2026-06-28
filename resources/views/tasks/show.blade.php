@extends('layouts.maestro')

@section('title', $task->title)

@section('content')
    <div class="mb-4">
        <div class="mb-3 flex flex-wrap items-center gap-2 sm:gap-3">
            <a href="{{ route('projects.show', $project) }}" class="text-xs text-text-muted hover:text-primary-light">← {{ $project->name }}</a>
            <x-maestro.badge kind="task_type" :value="$task->type" />
            <x-maestro.badge kind="task_status" :value="$task->status" />
            <x-maestro.badge kind="mode" :value="$task->mode" />
            <x-maestro.badge kind="priority" :value="$task->priority" />
        </div>
        <h1 class="text-sm font-bold text-text-primary">{{ $task->title }}</h1>
        @if($task->description)
            <p class="mt-1 max-w-3xl text-[11px] leading-relaxed text-text-muted">{{ $task->description }}</p>
        @endif
    </div>

    <div class="min-h-[50vh] lg:h-[calc(100vh-11rem)]">
        <x-maestro.task-detail-panels class="h-full">
            <x-slot:pipeline>
                @livewire('task-pipeline', ['task' => $task])
            </x-slot:pipeline>
            <x-slot:output>
                @livewire('step-output-viewer', ['task' => $task])
            </x-slot:output>
        </x-maestro.task-detail-panels>
    </div>
@endsection
