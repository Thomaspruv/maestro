@props(['userProjects' => collect(), 'currentProject' => null])

<aside {{ $attributes->merge(['class' => 'maestro-sidebar flex flex-col']) }}>
    {{-- Navigation mobile (drawer unifié) --}}
    <nav class="border-b px-2 py-3 lg:hidden">
        <x-ui.label class="mb-2 px-1">Navigation</x-ui.label>
        <ul class="space-y-0.5">
            <li>
                <a href="{{ route('dashboard') }}"
                   @click="mobileNavOpen = false"
                   @class([
                       'maestro-mobile-nav-item',
                       'maestro-mobile-nav-item-active' => request()->routeIs('dashboard'),
                   ])>
                    📊 Dashboard
                </a>
            </li>
            <li>
                <a href="{{ route('projects.index') }}"
                   @click="mobileNavOpen = false"
                   @class([
                       'maestro-mobile-nav-item',
                       'maestro-mobile-nav-item-active' => request()->routeIs('projects.*') && ! request()->routeIs('projects.costs.*') && ! request()->routeIs('projects.discovery'),
                   ])>
                    📁 Projets
                </a>
            </li>
            @isset($currentProject)
                <li>
                    <a href="{{ route('projects.discovery', $currentProject) }}"
                       @click="mobileNavOpen = false"
                       @class([
                           'maestro-mobile-nav-item',
                           'maestro-mobile-nav-item-active' => request()->routeIs('projects.discovery'),
                       ])>
                        🤖 Discovery IA
                    </a>
                </li>
            @endisset
            <li>
                <a href="{{ route('costs.global') }}"
                   @click="mobileNavOpen = false"
                   @class([
                       'maestro-mobile-nav-item',
                       'maestro-mobile-nav-item-active' => request()->routeIs('costs.global'),
                   ])>
                    💰 Coûts global
                </a>
            </li>
            <li>
                <a href="{{ route('settings.mcp') }}"
                   @click="mobileNavOpen = false"
                   @class([
                       'maestro-mobile-nav-item',
                       'maestro-mobile-nav-item-active' => request()->routeIs('settings.mcp'),
                   ])>
                    🔌 Intégrations
                </a>
            </li>
            <li>
                <a href="{{ route('settings.edit') }}"
                   @click="mobileNavOpen = false"
                   @class([
                       'maestro-mobile-nav-item',
                       'maestro-mobile-nav-item-active' => request()->routeIs('settings.edit'),
                   ])>
                    ⚙️ Paramètres
                </a>
            </li>
        </ul>
    </nav>

    <div class="border-b px-3 py-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <x-ui.label>Mes projets</x-ui.label>
            <a href="{{ route('projects.create') }}"
               @click="mobileNavOpen = false"
               class="rounded-md bg-maestro-accent/10 px-2 py-0.5 text-[12px] font-medium text-maestro-accent transition-colors hover:bg-maestro-accent/20"
               title="Nouveau projet">
                +
            </a>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-2 py-2 pb-4">
        @if($userProjects->isNotEmpty())
            <ul class="space-y-0.5">
                @foreach($userProjects as $project)
                    <li>
                        <a href="{{ route('projects.show', $project) }}"
                           @click="mobileNavOpen = false"
                           @class([
                               'maestro-project-item',
                               'maestro-project-item-active' => $currentProject && $currentProject->id === $project->id,
                           ])>
                            <span class="block truncate text-[13px] font-medium text-maestro-text">{{ $project->name }}</span>
                            <span class="block truncate text-[11px] text-maestro-subtle">{{ $project->github_repo }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="rounded-lg border border-dashed px-3 py-4 text-center">
                <p class="mb-2 text-[12px] text-maestro-subtle">Aucun projet actif</p>
                <a href="{{ route('projects.create') }}"
                   @click="mobileNavOpen = false"
                   class="text-[12px] font-medium text-maestro-accent hover:underline">
                    Créer un projet →
                </a>
            </div>
        @endif
    </nav>

    @isset($currentProject)
        <div class="hidden border-t p-3 lg:block">
            <x-maestro.discovery-button :project="$currentProject" size="full" />
        </div>
    @endisset
</aside>
