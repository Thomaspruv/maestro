@props(['currentProject' => null])

<header {{ $attributes->merge(['class' => 'maestro-global-topbar']) }}>
    <div class="flex min-w-0 items-center gap-3 lg:gap-6">
        <button
            type="button"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-md text-maestro-text lg:hidden"
            @click="mobileNavOpen = !mobileNavOpen"
            aria-label="Ouvrir le menu"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2 text-[16px] font-medium text-maestro-text">
            <span>⚒️</span>
            <span class="hidden sm:inline">Maestro</span>
            <span class="rounded bg-maestro-accent-muted px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wider text-maestro-accent-light">Beta</span>
        </a>

        <nav class="maestro-topnav hidden min-w-0 items-center gap-0.5 lg:flex">
            <a href="{{ route('dashboard') }}"
               class="maestro-topnav-item {{ request()->routeIs('dashboard') ? 'maestro-topnav-item-active' : '' }}">
                📊 Dashboard
            </a>
            <a href="{{ route('projects.index') }}"
               class="maestro-topnav-item {{ request()->routeIs('projects.*') && ! request()->routeIs('projects.costs.*') && ! request()->routeIs('projects.discovery') ? 'maestro-topnav-item-active' : '' }}">
                📁 Projets
            </a>
            @isset($currentProject)
                <a href="{{ route('projects.discovery', $currentProject) }}"
                   class="maestro-topnav-item {{ request()->routeIs('projects.discovery') ? 'maestro-topnav-item-active maestro-nav-discovery' : 'maestro-nav-discovery' }}">
                    <span class="discovery-btn-icon inline-flex h-4 w-4 items-center justify-center rounded text-[10px]">🤖</span>
                    Discovery IA
                </a>
            @endisset
            <a href="{{ route('costs.global') }}"
               class="maestro-topnav-item {{ request()->routeIs('costs.global') ? 'maestro-topnav-item-active' : '' }}">
                💰 Coûts global
            </a>
            <a href="{{ route('settings.mcp') }}"
               class="maestro-topnav-item {{ request()->routeIs('settings.mcp') ? 'maestro-topnav-item-active' : '' }}">
                🔌 Intégrations
            </a>
            <a href="{{ route('settings.edit') }}"
               class="maestro-topnav-item {{ request()->routeIs('settings.edit') ? 'maestro-topnav-item-active' : '' }}">
                ⚙️ Paramètres
            </a>
        </nav>
    </div>

    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        <p class="hidden max-w-[140px] truncate text-[12px] text-maestro-subtle sm:block">{{ auth()->user()->name }}</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-[12px] text-maestro-subtle hover:text-[var(--maestro-danger)]">Déconnexion</button>
        </form>
    </div>
</header>
