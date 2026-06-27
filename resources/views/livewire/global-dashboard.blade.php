<div>
    <div class="mb-5 grid grid-cols-4 gap-3">
        <x-ui.metric-card label="Projets actifs" :value="$projects->count()" />
        <x-ui.metric-card label="Tâches totales" :value="$totalTasks" />
        <x-ui.metric-card label="En cours" :value="$tasksInProgress" subColor="info" />
        <x-ui.metric-card
            label="Coût du mois"
            :value="'$'.number_format($currentMonthCost, 2)"
            :sub="$monthlyBudget > 0 ? 'Budget: $'.number_format($monthlyBudget, 2) : null"
            subColor="warning"
        />
    </div>

    <div class="grid grid-cols-2 gap-5">
        <x-ui.card>
            <div class="mb-3 flex items-center justify-between">
                <x-ui.heading-3>Projets</x-ui.heading-3>
                <x-ui.button variant="primary" size="sm" tag="a" href="{{ route('projects.create') }}">+ Nouveau</x-ui.button>
            </div>

            @forelse($projects as $project)
                <a href="{{ route('projects.show', $project) }}" class="mb-2 block rounded-lg border bg-maestro-surface-2 px-3 py-2 transition-colors hover:border-maestro-accent/40">
                    <div class="flex items-center justify-between">
                        <p class="text-[13px] font-medium text-maestro-text">{{ $project->name }}</p>
                        <span class="text-[12px] text-maestro-subtle">{{ $project->tasks_count }} tâches</span>
                    </div>
                    <div class="mt-1 flex gap-3 text-[12px] text-maestro-subtle">
                        <span>⚡ {{ $project->tasks_in_progress_count }} en cours</span>
                        <span>🚧 {{ $project->tasks_pending_gates_count }} gates</span>
                    </div>
                </a>
            @empty
                <x-maestro.empty-state title="Aucun projet" description="Créez votre premier projet pour commencer.">
                    <x-ui.button variant="primary" size="sm" tag="a" href="{{ route('projects.create') }}">Créer un projet</x-ui.button>
                </x-maestro.empty-state>
            @endforelse
        </x-ui.card>

        <x-ui.card>
            <x-ui.heading-3 class="mb-3">Activité récente</x-ui.heading-3>

            @forelse($recentTasks as $task)
                <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" class="mb-2 flex items-center justify-between rounded-lg border bg-maestro-surface-2 px-3 py-2 transition-colors hover:border-maestro-accent/40">
                    <div>
                        <p class="text-[13px] text-maestro-text">{{ $task->title }}</p>
                        <p class="text-[12px] text-maestro-subtle">{{ $task->project->name }}</p>
                    </div>
                    <x-maestro.badge kind="task_status" :value="$task->status" />
                </a>
            @empty
                <x-maestro.empty-state title="Aucune activité" icon="💤" class="border-0 py-6" />
            @endforelse
        </x-ui.card>
    </div>
</div>
