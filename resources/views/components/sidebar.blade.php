<aside {{ $attributes->merge(['class' => 'maestro-sidebar flex flex-col']) }}>
    <div class="border-b px-3 py-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <x-ui.label>Mes projets</x-ui.label>
            <a href="{{ route('projects.create') }}"
               class="rounded-md bg-maestro-accent/10 px-2 py-0.5 text-[12px] font-medium text-maestro-accent transition-colors hover:bg-maestro-accent/20"
               title="Nouveau projet">
                +
            </a>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-2 py-2">
        @if(isset($userProjects) && $userProjects->isNotEmpty())
            <ul class="space-y-0.5">
                @foreach($userProjects as $project)
                    <li>
                        <a href="{{ route('projects.show', $project) }}"
                           @class([
                               'maestro-project-item',
                               'maestro-project-item-active' => isset($currentProject) && $currentProject->id === $project->id,
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
                <a href="{{ route('projects.create') }}" class="text-[12px] font-medium text-maestro-accent hover:underline">
                    Créer un projet →
                </a>
            </div>
        @endif
    </nav>

    @isset($currentProject)
        <div class="border-t p-3">
            <x-maestro.discovery-button :project="$currentProject" size="full" />
        </div>
    @endisset
</aside>
