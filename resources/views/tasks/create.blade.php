@extends('layouts.maestro')

@php
    $title = 'Nouvelle tâche';
@endphp

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="mb-4">
            <a href="{{ route('projects.show', $project) }}" class="text-xs text-text-muted hover:text-primary-light">← Retour au kanban</a>
        </div>

        <form method="POST" action="{{ route('projects.tasks.store', $project) }}" class="maestro-card p-5">
            @csrf

            <x-maestro.input name="title" label="Titre" :value="old('title')" :error="$errors->first('title')" required />
            <x-maestro.textarea name="description" label="Description" rows="4" :error="$errors->first('description')">{{ old('description') }}</x-maestro.textarea>
            <x-maestro.input name="module" label="Module" :value="old('module')" :error="$errors->first('module')" placeholder="ex: auth, billing..." />

            <div class="grid grid-cols-3 gap-3">
                <x-maestro.select name="type" label="Type" :error="$errors->first('type')">
                    <option value="feature" @selected(old('type', 'feature') === 'feature')>Fonctionnalité</option>
                    <option value="bug" @selected(old('type') === 'bug')>Bug</option>
                    <option value="improvement" @selected(old('type') === 'improvement')>Amélioration</option>
                    <option value="chore" @selected(old('type') === 'chore')>Maintenance</option>
                </x-maestro.select>

                <x-maestro.select name="priority" label="Priorité" :error="$errors->first('priority')">
                    <option value="critical" @selected(old('priority') === 'critical')>Critique</option>
                    <option value="high" @selected(old('priority') === 'high')>Haute</option>
                    <option value="medium" @selected(old('priority', 'medium') === 'medium')>Moyenne</option>
                    <option value="low" @selected(old('priority') === 'low')>Basse</option>
                </x-maestro.select>

                <x-maestro.select name="mode" label="Mode" :error="$errors->first('mode')">
                    <option value="manual" @selected(old('mode', $defaultMode) === 'manual')>Manuel</option>
                    <option value="semi_auto" @selected(old('mode', $defaultMode) === 'semi_auto')>Semi-auto</option>
                    <option value="full_auto" @selected(old('mode', $defaultMode) === 'full_auto')>Auto</option>
                </x-maestro.select>
            </div>

            <div class="mt-5">
                @livewire('cost-estimation-panel', ['project' => $project, 'defaultMode' => $defaultMode])
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <x-maestro.button variant="ghost" tag="a" href="{{ route('projects.show', $project) }}">Annuler</x-maestro.button>
                <x-maestro.button type="submit">Créer la tâche</x-maestro.button>
            </div>
        </form>
    </div>
@endsection
