@extends('layouts.maestro')

@section('title', 'Paramètres — '.$project->name)

@section('content')
    @livewire('project-settings', ['project' => $project])
@endsection
