<?php

namespace App\Livewire;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Services\KanbanColumnResolver;
use App\Services\OrchestratorService;
use App\Services\PipelineHealthService;
use App\Support\PipelineActivity;
use Livewire\Attributes\On;
use Livewire\Component;

class KanbanBoard extends Component
{
    public Project $project;

    public bool $polling = true;

    public string $search = '';

    public string $filterType = '';

    public string $filterPriority = '';

    public ?int $openTaskId = null;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    #[On('task-updated')]
    public function refreshBoard(): void
    {
        $this->project->refresh();
    }

    public function openTask(int $taskId): void
    {
        $task = Task::query()
            ->where('project_id', $this->project->id)
            ->findOrFail($taskId);

        $this->authorize('view', $task);
        $this->openTaskId = $taskId;
    }

    public function closeTask(): void
    {
        $this->openTaskId = null;
    }

    public function startTask(int $taskId, OrchestratorService $orchestrator, KanbanColumnResolver $resolver): void
    {
        $task = Task::query()
            ->where('project_id', $this->project->id)
            ->findOrFail($taskId);

        $this->authorize('update', $task);

        if (OrchestratorService::internalPipelineEnabled()) {
            $resolver->applyColumn($task, 'pm');
            $orchestrator->advance($task->fresh());
        } else {
            $orchestrator->handoffToHermes($task);
        }

        $this->openTaskId = $taskId;
    }

    public function updateTaskStatus(int $taskId, string $status): void
    {
        $task = Task::where('project_id', $this->project->id)->findOrFail($taskId);
        $this->authorize('update', $task);

        $task->update(['status' => $status]);
    }

    /**
     * @param  array<int, array{task_id: int, sort_order: int}>  $items
     */
    public function updateColumnOrder(string $column, array $items): void
    {
        $this->syncKanbanColumns([$column => $items]);
    }

    /**
     * Synchronise toutes les colonnes visibles en un seul appel (évite les courses Sortable).
     *
     * @param  array<string, array<int, array{task_id: int, sort_order: int}>>  $columns
     */
    public function syncKanbanColumns(array $columns, KanbanColumnResolver $resolver): void
    {
        foreach ($columns as $columnSlug => $items) {
            if (! is_array($items) || ! $resolver->isValidColumn($columnSlug)) {
                continue;
            }

            foreach ($items as $item) {
                if (! is_array($item) || ! isset($item['task_id'])) {
                    continue;
                }

                $task = Task::where('project_id', $this->project->id)->find($item['task_id']);

                if ($task === null) {
                    continue;
                }

                $this->authorize('update', $task);

                $currentColumn = $resolver->resolveColumn($task);

                if ($currentColumn !== $columnSlug) {
                    $resolver->applyColumn($task, $columnSlug);
                    $task->refresh();
                }

                $task->update([
                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                ]);
            }
        }
    }

    public function render(KanbanColumnResolver $resolver)
    {
        $query = $this->project->tasks()
            ->with(['pipelineSteps', 'gates'])
            ->orderBy('sort_order');

        if ($this->search) {
            $query->where('title', 'like', '%'.$this->search.'%');
        }
        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }
        if ($this->filterPriority) {
            $query->where('priority', $this->filterPriority);
        }

        $tasks = $query->get();
        $columns = $resolver->groupTasksByColumn($tasks);
        $kanbanColumns = $resolver->columns();

        $allProjectTasks = $this->project->tasks()->get();
        $groupedAll = $resolver->groupTasksByColumn($allProjectTasks);

        $stats = [
            'total' => $allProjectTasks->count(),
            'backlog' => $groupedAll['backlog']->count(),
            'dev' => $groupedAll['dev']->count(),
            'in_pipeline' => collect($groupedAll)
                ->except(['backlog', 'dev', 'done'])
                ->flatten()
                ->count(),
            'pending_gates' => $this->project->tasks()->whereHas('gates', fn ($q) => $q->where('status', 'pending'))->count(),
            'total_cost' => (float) $this->project->tasks()->sum('actual_cost'),
        ];

        $openTask = $this->openTaskId
            ? Task::query()
                ->where('project_id', $this->project->id)
                ->with(['pipelineSteps', 'gates.pipelineStep', 'project'])
                ->find($this->openTaskId)
            : null;

        $healthService = app(PipelineHealthService::class);
        $workerBanner = $healthService->kanbanWorkerBanner($this->project);
        $taskHealthMap = $tasks->mapWithKeys(
            fn (Task $task) => [$task->id => $healthService->forTask($task)]
        );

        return view('livewire.kanban-board', [
            'columns' => $columns,
            'kanbanColumns' => $kanbanColumns,
            'stats' => $stats,
            'taskTypes' => TaskType::cases(),
            'priorities' => TaskPriority::cases(),
            'openTask' => $openTask,
            'workerBanner' => $workerBanner,
            'taskHealthMap' => $taskHealthMap,
            'boardShouldPoll' => $this->polling && ! $this->openTaskId,
            'shouldPoll' => $this->polling || ($openTask && PipelineActivity::shouldPoll($openTask)),
        ]);
    }
}
