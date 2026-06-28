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
<body
    class="bg-maestro-bg text-maestro-text min-h-screen"
    x-data="{ mobileNavOpen: false }"
    x-bind:class="{ 'overflow-hidden': mobileNavOpen }"
    @keydown.escape.window="mobileNavOpen = false"
>
    <x-topbar :current-project="$currentProject ?? null" />

    <div
        x-show="mobileNavOpen"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="maestro-drawer-backdrop"
        @click="mobileNavOpen = false"
        aria-hidden="true"
    ></div>

    <x-sidebar
        :user-projects="$userProjects ?? collect()"
        :current-project="$currentProject ?? null"
        x-bind:class="{ 'maestro-sidebar-open': mobileNavOpen }"
    />

    <div class="maestro-content">
        <header class="maestro-topbar -mx-4 -mt-4 mb-5 lg:-mx-6 lg:-mt-6">
            <h1 class="text-[16px] font-medium text-maestro-text">@yield('title', $title ?? 'Maestro')</h1>
            <div class="flex flex-wrap items-center gap-2">
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
