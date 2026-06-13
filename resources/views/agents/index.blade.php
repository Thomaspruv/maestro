@extends('layouts.maestro')

@section('title', 'Agents — Maestro')

@section('content')
    <div class="mb-6">
        <h1 class="text-lg font-bold text-text-primary">Agents</h1>
        <p class="text-xs text-text-muted">Gérez votre bibliothèque d'agents IA</p>
    </div>

    @livewire('agents-index')
@endsection
