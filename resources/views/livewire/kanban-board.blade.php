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
    <div class="mb-5 grid grid-cols-4 gap-3">
        <x-ui.metric-card label="Tâches totales" :value="$stats['total']" />
        <x-ui.metric-card label="En cours" :value="$stats['in_progress']" subColor="info" />
        <x-ui.metric-card label="Gates en attente" :value="$stats['pending_gates']" subColor="warning" />
        <x-ui.metric-card label="Coût réel" :value="'$'.number_format($stats['total_cost'], 2)" subColor="warning" />
    </div>

    {{-- Filtres --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-maestro.input wire:model.live.debounce.300ms="search" placeholder="Rechercher une tâche..." class="max-w-xs" />
        <x-maestro.select wire:model.live="filterType" class="max-w-[140px]">
            <option value="">Tous les types</option>
            @foreach($taskTypes as $type)
                <option value="{{ $type->value }}">{{ $type->value }}</option>
            @endforeach
        </x-maestro.select>
        <x-maestro.select wire:model.live="filterPriority" class="max-w-[140px]">
            <option value="">Toutes priorités</option>
            @foreach($priorities as $priority)
                <option value="{{ $priority->value }}">{{ $priority->value }}</option>
            @endforeach
        </x-maestro.select>
        <label class="ml-auto flex items-center gap-2 text-xs text-text-muted">
            <input type="checkbox" wire:model.live="polling" class="rounded border-bg-overlay">
            Rafraîchissement auto
        </label>
    </div>

    {{-- Colonnes Kanban --}}
    @php
        $columnLabels = [
            'backlog' => ['label' => 'Backlog', 'icon' => '📥'],
            'in_progress' => ['label' => 'En cours', 'icon' => '⚡'],
            'in_review' => ['label' => 'En revue', 'icon' => '👀'],
            'done' => ['label' => 'Terminé', 'icon' => '✅'],
        ];
    @endphp

    <div class="grid grid-cols-4 gap-3">
        @foreach($columnLabels as $status => $meta)
            <div class="flex flex-col">
                <div class="mb-2 flex items-center gap-2 px-1">
                    <span>{{ $meta['icon'] }}</span>
                    <span class="text-[13px] font-medium text-maestro-text">{{ $meta['label'] }}</span>
                    <span class="rounded bg-maestro-surface-2 px-1.5 py-0.5 text-[12px] text-maestro-subtle">{{ $columns[$status]->count() }}</span>
                </div>

                <div
                    wire:ignore
                    class="kanban-column min-h-[400px] flex-1 space-y-2 rounded-lg border bg-maestro-surface p-2"
                    data-status="{{ $status }}"
                    id="kanban-{{ $status }}"
                >
                    @forelse($columns[$status] as $task)
                        <div data-task-id="{{ $task->id }}" class="kanban-task-wrapper space-y-1.5">
                            <x-maestro.task-card
                                :task="$task"
                                :project="$project"
                                :health="$taskHealthMap[$task->id] ?? null"
                                wire:click="openTask({{ $task->id }})"
                                class="cursor-pointer transition-colors hover:border-primary/40"
                            />

                            @if($task->status->value === 'backlog')
                                <button
                                    type="button"
                                    wire:click.stop="startTask({{ $task->id }})"
                                    class="maestro-btn-primary w-full py-1.5 text-[10px]"
                                >
                                    ▶ Lancer la pipeline
                                </button>
                            @else
                                <button
                                    type="button"
                                    wire:click.stop="openTask({{ $task->id }})"
                                    class="maestro-btn-ghost w-full py-1 text-[10px]"
                                >
                                    Voir la pipeline →
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

    {{-- Panneau pipeline (depuis le Kanban) --}}
    @if($openTask)
        <div class="task-drawer-backdrop" wire:click="closeTask" aria-hidden="true"></div>
        <div class="task-drawer" role="dialog" aria-labelledby="task-drawer-title">
            <div class="flex items-start justify-between gap-3 border-b px-5 py-4">
                <div class="min-w-0">
                    <x-ui.label>Pipeline en direct</x-ui.label>
                    <h2 id="task-drawer-title" class="truncate text-[16px] font-medium text-maestro-text">{{ $openTask->title }}</h2>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <x-maestro.badge kind="task_status" :value="$openTask->status" />
                        <x-maestro.badge kind="task_type" :value="$openTask->type" />
                        <x-maestro.badge kind="mode" :value="$openTask->mode" />
                    </div>
                </div>
                <div class="flex shrink-0 gap-2">
                    <a
                        href="{{ route('projects.tasks.show', [$project, $openTask]) }}"
                        class="maestro-btn-ghost px-2 py-1 text-[10px]"
                    >
                        Plein écran
                    </a>
                    <button type="button" wire:click="closeTask" class="maestro-btn-ghost px-2 py-1 text-[10px]">✕</button>
                </div>
            </div>

            <div class="task-drawer-body grid min-h-0 flex-1 grid-cols-[minmax(240px,280px)_1fr] gap-0">
                <div class="overflow-y-auto border-r p-4">
                    @livewire('task-pipeline', ['task' => $openTask], key('drawer-pipeline-'.$openTask->id))
                </div>
                <div class="flex h-full min-h-0 flex-col overflow-hidden p-4">
                    @livewire('agent-output-viewer', ['task' => $openTask], key('drawer-output-'.$openTask->id))
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('livewire:navigated', initKanbanSortable);
document.addEventListener('DOMContentLoaded', initKanbanSortable);

function initKanbanSortable() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        if (col._sortable) return;
        const status = col.dataset.status;
        col._sortable = Sortable.create(col, {
            group: 'kanban',
            animation: 150,
            draggable: '.kanban-task-wrapper',
            onEnd: () => {
                const items = [...col.querySelectorAll('[data-task-id]')].map((el, i) => ({
                    task_id: parseInt(el.dataset.taskId),
                    sort_order: i,
                }));
                @this.call('updateColumnOrder', status, items);
            },
        });
    });
}
</script>
@endpush
