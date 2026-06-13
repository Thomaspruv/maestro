<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Nouveau projet — Maestro' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-bg-base">
    <div class="mx-auto min-h-screen max-w-[680px] px-5 py-8">
        <div class="mb-6 flex items-center justify-between">
            <a href="{{ route('projects.index') }}" class="flex items-center gap-2 text-sm font-bold text-text-primary">
                <span>⚒️</span>
                <span>Maestro</span>
            </a>
            @isset($step)
                <span class="text-[10px] text-text-muted">Étape {{ $step }} / 4</span>
            @endisset
        </div>

        @isset($progress)
            <div class="maestro-progress-bar mb-6">
                <div class="maestro-progress-fill" style="width: {{ $progress }}%"></div>
            </div>
        @endisset

        @if(session('success'))
            <div class="mb-4 rounded-md border border-success/30 bg-success-muted px-3 py-2 text-xs text-success">
                {{ session('success') }}
            </div>
        @endif

        @yield('content')
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
