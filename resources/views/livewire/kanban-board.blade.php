<div @if($polling) wire:poll.5s @endif>
    {{-- Lancement Discovery --}}
    <div class="mb-5">
        <x-maestro.discovery-button :project="$project" size="banner" />
    </div>

    {{-- Statistiques --}}
    <div class="mb-5 grid grid-cols-4 gap-3">
        <x-maestro.stat-card label="Tâches totales" :value="$stats['total']" icon="📋" />
        <x-maestro.stat-card label="En cours" :value="$stats['in_progress']" icon="⚡" />
        <x-maestro.stat-card label="Gates en attente" :value="$stats['pending_gates']" icon="🚧" />
        <x-maestro.stat-card label="Coût réel" :value="'$'.number_format($stats['total_cost'], 2)" icon="💰" />
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
                    <span class="text-xs font-semibold text-text-primary">{{ $meta['label'] }}</span>
                    <span class="rounded bg-bg-overlay px-1.5 py-0.5 text-[10px] text-text-muted">{{ $columns[$status]->count() }}</span>
                </div>

                <div
                    wire:ignore
                    class="kanban-column min-h-[400px] flex-1 space-y-2 rounded-lg border border-bg-overlay bg-bg-surface/50 p-2"
                    data-status="{{ $status }}"
                    id="kanban-{{ $status }}"
                >
                    @forelse($columns[$status] as $task)
                        <x-maestro.task-card :task="$task" :project="$project" />
                    @empty
                        <x-maestro.empty-state title="Vide" icon="📭" class="py-6" />
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
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
            draggable: '[data-task-id]',
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
