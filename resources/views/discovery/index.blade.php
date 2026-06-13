@extends('layouts.maestro')

@section('title', 'Discovery IA — '.$project->name)

@section('content')
    <div class="mb-5 flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <span class="discovery-btn-icon flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-muted text-2xl">
                🤖
            </span>
            <div>
                <h2 class="text-sm font-bold text-text-primary">Discovery IA</h2>
                <p class="mt-1 max-w-xl text-xs text-text-muted">
                    Lancez une Discovery complète : analyse du code, veille marché et backlog
                    pour proposer des features et améliorations fonctionnelles.
                </p>
            </div>
        </div>
        <a href="{{ route('projects.show', $project) }}" class="text-[10px] text-text-muted hover:text-primary-light">
            ← Retour au board
        </a>
    </div>

    @livewire('discovery-chat', ['project' => $project])
@endsection
