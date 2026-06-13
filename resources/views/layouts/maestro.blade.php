<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Maestro' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <div class="desktop-only-message">
        <div>
            <div class="mb-3 text-4xl">⚒️</div>
            <h1 class="mb-2 text-lg font-semibold text-text-primary">Maestro — Bureau uniquement</h1>
            <p class="max-w-sm text-sm">Cette interface nécessite un écran d'au moins 1200px de large.</p>
        </div>
    </div>

    <aside class="maestro-sidebar flex flex-col">
        <div class="border-b border-bg-overlay px-4 py-4">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-sm font-bold text-text-primary">
                <span>⚒️</span>
                <span>Maestro</span>
                <span class="rounded bg-primary-muted px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-primary-light">Beta</span>
            </a>
        </div>

        <nav class="flex-1 space-y-0.5 px-2 py-3">
            <a href="{{ route('dashboard') }}"
               class="maestro-nav-item {{ request()->routeIs('dashboard') ? 'maestro-nav-item-active' : '' }}">
                📊 Dashboard
            </a>
            <a href="{{ route('projects.index') }}"
               class="maestro-nav-item {{ request()->routeIs('projects.*') && ! request()->routeIs('projects.costs.*') ? 'maestro-nav-item-active' : '' }}">
                📁 Projets
            </a>
            <a href="{{ route('costs.global') }}"
               class="maestro-nav-item {{ request()->routeIs('costs.global') ? 'maestro-nav-item-active' : '' }}">
                💰 Coûts global
            </a>
            <a href="{{ route('settings.edit') }}"
               class="maestro-nav-item {{ request()->routeIs('settings.*') ? 'maestro-nav-item-active' : '' }}">
                ⚙️ Paramètres
            </a>
        </nav>

        @isset($currentProject)
            <div class="border-t border-bg-overlay p-3">
                <p class="maestro-section-title mb-2">Projet actif</p>
                <div class="maestro-card px-3 py-2">
                    <p class="truncate text-xs font-semibold text-text-primary">{{ $currentProject->name }}</p>
                    <p class="truncate text-[10px] text-text-muted">{{ $currentProject->github_repo }}</p>
                    @if(isset($userProjects) && $userProjects->count() > 1)
                        <select
                            class="maestro-input mt-2 py-1 text-[10px]"
                            onchange="if (this.value) window.location.href = this.value"
                        >
                            @foreach($userProjects as $p)
                                <option value="{{ route('projects.show', $p) }}" @selected($p->id === $currentProject->id)>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>
        @endisset

        <div class="border-t border-bg-overlay p-3">
            <p class="truncate text-[10px] text-text-muted">{{ auth()->user()->name }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="text-[10px] text-text-faint hover:text-danger">Déconnexion</button>
            </form>
        </div>
    </aside>

    <div class="maestro-content">
        <header class="maestro-topbar -mx-5 -mt-5 mb-5">
            <h1 class="text-sm font-semibold text-text-primary">@yield('title', $title ?? 'Maestro')</h1>
            <div class="flex items-center gap-2">
                @hasSection('actions')
                    @yield('actions')
                @elseif(isset($actions))
                    {{ $actions }}
                @endif
            </div>
        </header>

        @if(session('success'))
            <div class="mb-4 rounded-md border border-success/30 bg-success-muted px-3 py-2 text-xs text-success">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-md border border-danger/30 bg-danger-muted px-3 py-2 text-xs text-danger">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
