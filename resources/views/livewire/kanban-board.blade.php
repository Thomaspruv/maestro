<div @if($boardShouldPoll) wire:poll.5s @endif>
    @if($workerBanner['show'] ?? false)
        @php
            $bannerClass = match ($workerBanner['tone']) {
                'danger' => 'pipeline-health-danger',
                'warning' => 'pipeline-health-warning',
                'success' => 'pipeline-health-success',
                default => 'pipeline-health-muted',
            };
        @endphp
        <div class="mb-4 rounded-lg border px-4 py-3 {{ $bannerClass }}">
            <p class="text-[13px] font-medium">{{ $workerBanner['title'] }}</p>
            <p class="mt-1 text-[12px] leading-relaxed opacity-90">{{ $workerBanner['message'] }}</p>
            @if(($workerBanner['show_horizon_link'] ?? false) && config('queue.default') === 'redis')
                <a href="{{ url('/horizon') }}" target="_blank" class="mt-2 inline-block text-[12px] font-medium underline">
                    Ouvrir Horizon →
                </a>
            @endif
        </div>
    @endif

    {{-- Lancement Discovery --}}
    <div class="mb-5">
        <x-maestro.discovery-button :project="$project" size="banner" />
    </div>

    {{-- Statistiques --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-ui.metric-card label="Tâches totales" :value="$stats['total']" />
        <x-ui.metric-card label="Backlog" :value="$stats['backlog']" />
        <x-ui.metric-card label="Pipeline" :value="$stats['in_pipeline']" subColor="info" />
        <x-ui.metric-card label="Dev" :value="$stats['dev']" subColor="info" />
        <x-ui.metric-card label="Gates en attente" :value="$stats['pending_gates']" subColor="warning" />
        <x-ui.metric-card label="Coût réel" :value="'$'.number_format($stats['total_cost'], 2)" subColor="warning" />
    </div>

    {{-- Filtres --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <x-maestro.input wire:model.live.debounce.300ms="search" placeholder="Rechercher une tâche..." class="w-full sm:max-w-xs" />
        <x-maestro.select wire:model.live="filterType" class="w-full sm:max-w-[140px]">
            <option value="">Tous les types</option>
            @foreach($taskTypes as $type)
                <option value="{{ $type->value }}">{{ $type->value }}</option>
            @endforeach
        </x-maestro.select>
        <x-maestro.select wire:model.live="filterPriority" class="w-full sm:max-w-[140px]">
            <option value="">Toutes priorités</option>
            @foreach($priorities as $priority)
                <option value="{{ $priority->value }}">{{ $priority->value }}</option>
            @endforeach
        </x-maestro.select>
        <label class="flex items-center gap-2 text-xs text-text-muted sm:ml-auto">
            <input type="checkbox" wire:model.live="polling" class="rounded border-bg-overlay">
            Rafraîchissement auto
        </label>
    </div>

    {{-- Colonnes Kanban --}}
    <div class="overflow-x-auto pb-2">
        <div class="flex min-w-max gap-3">
        @foreach($kanbanColumns as $column)
            @php
                $slug = $column['slug'];
                $columnTasks = $columns[$slug] ?? collect();
            @endphp
            <div class="flex w-64 shrink-0 flex-col">
                <div class="mb-2 px-1">
                    <div class="flex items-center gap-2">
                        <span>{{ $column['emoji'] }}</span>
                        <span class="text-[13px] font-medium text-maestro-text">{{ $column['label'] }}</span>
                        <span class="rounded bg-maestro-surface-2 px-1.5 py-0.5 text-[12px] text-maestro-subtle">{{ $columnTasks->count() }}</span>
                    </div>
                    @if(! empty($column['hint']))
                        <p class="mt-0.5 text-[11px] text-maestro-subtle">{{ $column['hint'] }}</p>
                    @endif
                </div>

                <div
                    wire:ignore
                    class="kanban-column min-h-[400px] flex-1 space-y-2 rounded-lg border bg-maestro-surface p-2"
                    data-status="{{ $slug }}"
                    id="kanban-{{ $slug }}"
                >
                    @forelse($columnTasks as $task)
                        <div data-task-id="{{ $task->id }}" class="kanban-task-wrapper space-y-1.5">
                            <x-maestro.task-card
                                :task="$task"
                                :project="$project"
                                :health="$taskHealthMap[$task->id] ?? null"
                                wire:click="openTask({{ $task->id }})"
                                class="cursor-pointer transition-colors hover:border-primary/40"
                            />

                            @if($slug === 'backlog')
                                <button
                                    type="button"
                                    wire:click.stop="startTask({{ $task->id }})"
                                    class="maestro-btn-primary w-full py-1.5 text-[10px]"
                                >
                                    ▶ {{ config('maestro.internal_pipeline_enabled') ? 'Démarrer' : 'Envoyer à Hermes' }}
                                </button>
                            @elseif($slug === 'dev')
                                <button
                                    type="button"
                                    wire:click.stop="openTask({{ $task->id }})"
                                    class="maestro-btn-ghost w-full py-1 text-[10px]"
                                >
                                    Voir les specs →
                                </button>
                            @else
                                <button
                                    type="button"
                                    wire:click.stop="openTask({{ $task->id }})"
                                    class="maestro-btn-ghost w-full py-1 text-[10px]"
                                >
                                    Voir la progression →
                                </button>
                            @endif
                        </div>
                    @empty
                        <x-maestro.empty-state title="Vide" icon="📭" class="py-6" />
                    @endforelse
                </div>
            </div>
        @endforeach
        </div>
    </div>

    {{-- Panneau progression (depuis le Kanban) --}}
    @if($openTask)
        <div class="task-drawer-backdrop" wire:click="closeTask" aria-hidden="true"></div>
        <div class="task-drawer" role="dialog" aria-labelledby="task-drawer-title">
            <div class="flex flex-col gap-3 border-b px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                <div class="min-w-0">
                    <x-ui.label>Progression en direct</x-ui.label>
                    <h2 id="task-drawer-title" class="truncate text-[16px] font-medium text-maestro-text">{{ $openTask->title }}</h2>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <x-maestro.badge kind="task_status" :value="$openTask->status" />
                        <x-maestro.badge kind="task_type" :value="$openTask->type" />
                        <x-maestro.badge kind="mode" :value="$openTask->mode" />
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a
                        href="{{ route('projects.tasks.show', [$project, $openTask]) }}"
                        class="maestro-btn-ghost px-2 py-1 text-[10px]"
                    >
                        Plein écran
                    </a>
                    <button type="button" wire:click="closeTask" class="maestro-btn-ghost px-2 py-1 text-[10px]">✕</button>
                </div>
            </div>

            <div class="task-drawer-body flex min-h-0 flex-1 flex-col p-4">
                <x-maestro.task-detail-panels class="h-full min-h-0 flex-1">
                    <x-slot:pipeline>
                        @livewire('task-pipeline', ['task' => $openTask], key('drawer-pipeline-'.$openTask->id))
                    </x-slot:pipeline>
                    <x-slot:output>
                        @livewire('step-output-viewer', ['task' => $openTask], key('drawer-output-'.$openTask->id))
                    </x-slot:output>
                </x-maestro.task-detail-panels>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('livewire:navigated', initKanbanSortable);
document.addEventListener('DOMContentLoaded', initKanbanSortable);

function collectKanbanColumns() {
    const columns = {};

    document.querySelectorAll('.kanban-column').forEach(col => {
        const status = col.dataset.status;
        columns[status] = [...col.querySelectorAll('.kanban-task-wrapper[data-task-id]')].map((el, i) => ({
            task_id: parseInt(el.dataset.taskId, 10),
            sort_order: i,
        }));
    });

    return columns;
}

function syncKanbanToServer(component) {
    const columns = collectKanbanColumns();
    component.call('syncKanbanColumns', columns);
}

function initKanbanSortable() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        if (col._sortable) {
            col._sortable.destroy();
        }

        col._sortable = Sortable.create(col, {
            group: 'kanban',
            animation: 150,
            draggable: '.kanban-task-wrapper',
            ghostClass: 'opacity-40',
            onEnd: () => {
                const root = col.closest('[wire\\:id]');
                const component = root ? Livewire.find(root.getAttribute('wire:id')) : null;

                if (component) {
                    syncKanbanToServer(component);
                }
            },
        });
    });
}
</script>
@endpush
