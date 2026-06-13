<?php

namespace App\Livewire;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
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

    public function startTask(int $taskId, OrchestratorService $orchestrator): void
    {
        $task = Task::query()
            ->where('project_id', $this->project->id)
            ->findOrFail($taskId);

        $this->authorize('update', $task);

        $task->update([
            'status' => TaskStatus::InProgress,
            'current_agent' => null,
        ]);

        $orchestrator->advance($task->fresh());
        $this->openTaskId = $taskId;
    }

    public function updateTaskStatus(int $taskId, string $status): void
    {
        $task = Task::where('project_id', $this->project->id)->findOrFail($taskId);
        $this->authorize('update', $task);

        $task->update(['status' => TaskStatus::from($status)]);
    }

    /**
     * @param  array<int, array{task_id: int, sort_order: int}>  $items
     */
    public function updateColumnOrder(string $status, array $items): void
    {
        foreach ($items as $item) {
            $task = Task::where('project_id', $this->project->id)->find($item['task_id']);
            if ($task) {
                $this->authorize('update', $task);
                $task->update([
                    'status' => TaskStatus::from($status),
                    'sort_order' => $item['sort_order'],
                ]);
            }
        }
    }

    public function render()
    {
        $query = $this->project->tasks()
            ->with(['agentRuns', 'gates'])
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

        $columns = [
            'backlog' => $tasks->where('status', TaskStatus::Backlog)->merge($tasks->where('status', TaskStatus::Failed)),
            'in_progress' => $tasks->where('status', TaskStatus::InProgress),
            'in_review' => $tasks->where('status', TaskStatus::InReview),
            'done' => $tasks->where('status', TaskStatus::Done),
        ];

        $stats = [
            'total' => $this->project->tasks()->count(),
            'in_progress' => $this->project->tasks()->where('status', TaskStatus::InProgress)->count(),
            'pending_gates' => $this->project->tasks()->whereHas('gates', fn ($q) => $q->where('status', 'pending'))->count(),
            'total_cost' => (float) $this->project->tasks()->sum('actual_cost'),
        ];

        $openTask = $this->openTaskId
            ? Task::query()
                ->where('project_id', $this->project->id)
                ->with(['agentRuns', 'gates.agentRun', 'project'])
                ->find($this->openTaskId)
            : null;

        $healthService = app(PipelineHealthService::class);
        $workerBanner = $healthService->kanbanWorkerBanner($this->project);
        $taskHealthMap = $tasks->mapWithKeys(
            fn (Task $task) => [$task->id => $healthService->forTask($task)]
        );

        return view('livewire.kanban-board', [
            'columns' => $columns,
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
