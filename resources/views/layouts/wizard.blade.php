<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Nouveau projet — Maestro' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-maestro-bg text-maestro-text">
    <div class="mx-auto min-h-screen max-w-[680px] px-5 py-8">
        <div class="mb-6 flex items-center justify-between">
            <a href="{{ route('projects.index') }}" class="flex items-center gap-2 text-[16px] font-medium text-maestro-text">
                <span>⚒️</span>
                <span>Maestro</span>
            </a>
            @isset($step)
                <span class="text-[12px] text-maestro-subtle">Étape {{ $step }} / 4</span>
            @endisset
        </div>

        @isset($progress)
            <div class="maestro-progress-bar mb-6">
                <div class="maestro-progress-fill" style="width: {{ $progress }}%"></div>
            </div>
        @endisset

        @if(session('success'))
            <div class="mb-4 rounded-lg border px-3 py-2 text-[12px]" style="border-color: var(--maestro-success-border); background: var(--maestro-success-bg); color: var(--maestro-success);">
                {{ session('success') }}
            </div>
        @endif

        @yield('content')
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
