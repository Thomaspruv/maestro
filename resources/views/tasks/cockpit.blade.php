@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900">
    {{-- Header --}}
    <div class="bg-gray-950 border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white">{{ $task->title }}</h1>
                    <p class="text-gray-400 mt-1">{{ $project->name }}</p>
                </div>
                <div class="text-right">
                    <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-medium transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back to Task
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Content --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <livewire:pipeline-cockpit :task="$task" />
    </div>
</div>
@endsection
