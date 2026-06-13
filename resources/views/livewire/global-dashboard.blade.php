<div>
    <div class="mb-5 grid grid-cols-4 gap-3">
        <x-maestro.stat-card label="Projets actifs" :value="$projects->count()" icon="📁" />
        <x-maestro.stat-card label="Tâches totales" :value="$totalTasks" icon="📋" />
        <x-maestro.stat-card label="En cours" :value="$tasksInProgress" icon="⚡" />
        <x-maestro.stat-card
            label="Coût du mois"
            :value="'$'.number_format($currentMonthCost, 2)"
            :hint="$monthlyBudget > 0 ? 'Budget: $'.number_format($monthlyBudget, 2) : null"
            icon="💰"
        />
    </div>

    <div class="grid grid-cols-2 gap-5">
        {{-- Projets --}}
        <div class="maestro-card p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-xs font-semibold text-text-primary">Projets</h2>
                <x-maestro.button tag="a" href="{{ route('projects.create') }}" class="text-[10px]">+ Nouveau</x-maestro.button>
            </div>

            @forelse($projects as $project)
                <a href="{{ route('projects.show', $project) }}" class="mb-2 block rounded-md border border-bg-overlay bg-bg-surface px-3 py-2 transition-colors hover:border-primary/40">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold text-text-primary">{{ $project->name }}</p>
                        <span class="text-[10px] text-text-muted">{{ $project->tasks_count }} tâches</span>
                    </div>
                    <div class="mt-1 flex gap-3 text-[10px] text-text-muted">
                        <span>⚡ {{ $project->tasks_in_progress_count }} en cours</span>
                        <span>🚧 {{ $project->tasks_pending_gates_count }} gates</span>
                    </div>
                </a>
            @empty
                <x-maestro.empty-state title="Aucun projet" description="Créez votre premier projet pour commencer.">
                    <x-maestro.button tag="a" href="{{ route('projects.create') }}">Créer un projet</x-maestro.button>
                </x-maestro.empty-state>
            @endforelse
        </div>

        {{-- Tâches récentes --}}
        <div class="maestro-card p-4">
            <h2 class="mb-3 text-xs font-semibold text-text-primary">Activité récente</h2>

            @forelse($recentTasks as $task)
                <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" class="mb-2 flex items-center justify-between rounded-md border border-bg-overlay bg-bg-surface px-3 py-2 transition-colors hover:border-primary/40">
                    <div>
                        <p class="text-xs text-text-primary">{{ $task->title }}</p>
                        <p class="text-[10px] text-text-muted">{{ $task->project->name }}</p>
                    </div>
                    <x-maestro.badge kind="task_status" :value="$task->status" />
                </a>
            @empty
                <x-maestro.empty-state title="Aucune activité" icon="💤" class="py-6 border-0" />
            @endforelse
        </div>
    </div>
</div>
