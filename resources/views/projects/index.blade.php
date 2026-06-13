@extends('layouts.maestro')

@section('title', 'Projets')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <p class="text-xs text-text-muted">{{ $projects->count() }} projet(s) actif(s)</p>
        <x-maestro.button tag="a" href="{{ route('projects.create') }}">+ Nouveau projet</x-maestro.button>
    </div>

    @if($projects->isEmpty())
        <x-maestro.empty-state
            title="Aucun projet"
            description="Créez votre premier projet Maestro pour orchestrer vos agents IA."
            icon="📁"
        >
            <x-maestro.button tag="a" href="{{ route('projects.create') }}">Créer un projet</x-maestro.button>
        </x-maestro.empty-state>
    @else
        <div class="grid grid-cols-3 gap-4">
            @foreach($projects as $project)
                <a href="{{ route('projects.show', $project) }}" class="maestro-card block p-4 transition-colors hover:border-primary/40">
                    <h2 class="mb-1 text-sm font-semibold text-text-primary">{{ $project->name }}</h2>
                    @if($project->description)
                        <p class="mb-3 line-clamp-2 text-[11px] text-text-muted">{{ $project->description }}</p>
                    @endif
                    <div class="flex items-center justify-between text-[10px] text-text-muted">
                        <span>{{ $project->github_repo }}</span>
                        <span>{{ $project->tasks_count }} tâches</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
