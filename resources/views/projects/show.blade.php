@extends('layouts.maestro')

@section('title', $project->name)

@section('actions')
    <x-maestro.button variant="ghost" tag="a" href="{{ route('projects.settings.edit', $project) }}">⚙️ Paramètres</x-maestro.button>
    <x-maestro.button variant="ghost" tag="a" href="{{ route('projects.costs.index', $project) }}">💰 Coûts</x-maestro.button>
    <x-maestro.button tag="a" href="{{ route('projects.tasks.create', $project) }}">+ Nouvelle tâche</x-maestro.button>
@endsection

@section('content')
    @livewire('kanban-board', ['project' => $project])
@endsection
