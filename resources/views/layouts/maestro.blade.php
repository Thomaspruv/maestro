<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Maestro' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-maestro-bg text-maestro-text min-h-screen">
    <div class="desktop-only-message">
        <div>
            <div class="mb-3 text-4xl">⚒️</div>
            <h1 class="mb-2 text-lg font-medium text-maestro-text">Maestro — Bureau uniquement</h1>
            <p class="max-w-sm text-[12px] text-maestro-muted">Cette interface nécessite un écran d'au moins 1200px de large.</p>
        </div>
    </div>

    <x-topbar />

    <x-sidebar />

    <div class="maestro-content">
        <header class="maestro-topbar -mx-5 -mt-5 mb-5">
            <h1 class="text-[16px] font-medium text-maestro-text">@yield('title', $title ?? 'Maestro')</h1>
            <div class="flex items-center gap-2">
                @hasSection('actions')
                    @yield('actions')
                @elseif(isset($actions))
                    {{ $actions }}
                @endif
            </div>
        </header>

        @if(session('success'))
            <div class="mb-4 rounded-lg border px-3 py-2 text-[12px]" style="border-color: var(--maestro-success-border); background: var(--maestro-success-bg); color: var(--maestro-success);">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg border px-3 py-2 text-[12px]" style="border-color: var(--maestro-danger-border); background: var(--maestro-danger-bg); color: var(--maestro-danger);">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
