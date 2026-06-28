<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Maestro')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-maestro-bg text-maestro-text antialiased">
    <header class="border-b border-md bg-maestro-bg">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3 lg:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-2 text-[16px] font-medium text-maestro-text">
                <span>⚒️</span>
                <span>Maestro</span>
            </a>
            @auth
                <a href="{{ route('dashboard') }}" class="text-[12px] text-maestro-subtle hover:text-maestro-accent">
                    Tableau de bord
                </a>
            @else
                <a href="{{ route('login') }}" class="maestro-btn-secondary text-[11px]">
                    Connexion
                </a>
            @endauth
        </div>
    </header>

    <main class="px-4 py-6 lg:px-6">
        @yield('content')
    </main>
</body>
</html>
