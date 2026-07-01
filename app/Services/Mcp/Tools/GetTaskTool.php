<?php

namespace App\Services\Mcp\Tools;

use App\Enums\PipelineStepStatus;
use App\Models\User;
use App\Services\KanbanColumnResolver;
use App\Services\PipelineOutputCondenser;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\HermesTaskPresenter;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\ResolvesMcpResources;

class GetTaskTool implements McpTool
{
    use ResolvesMcpResources;

    public function __construct(
        private readonly PipelineOutputCondenser $condenser,
        private readonly HermesTaskPresenter $hermesPresenter,
        private readonly KanbanColumnResolver $resolver,
    ) {}

    public function name(): string
    {
        return 'get_task';
    }

    public function description(): string
    {
        return 'Détail d\'une tâche avec les outputs des agents précédents (condensés).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer', 'description' => 'ID de la tâche'],
            ],
            'required' => ['task_id'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        if (! isset($arguments['task_id'])) {
            throw McpToolException::missing('task_id');
        }

        $task = $this->findUserTask($user, (int) $arguments['task_id']);
        $task->load(['project:id,name,uuid,github_repo,github_branch', 'pipelineSteps' => fn ($q) => $q->orderBy('id')]);

        $pipelineSteps = $task->pipelineSteps
            ->whereIn('status', [PipelineStepStatus::Completed, PipelineStepStatus::Skipped])
            ->map(fn ($run) => [
                'id' => $run->id,
                'role' => $run->role,
                'status' => $run->status->value,
                'model' => $run->model,
                'output' => $this->condenser->condense($run->edited_output ?? $run->output ?? ''),
                'cost' => (float) $run->cost,
                'completed_at' => $run->completed_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'task' => [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'project_id' => $task->project_id,
                'project_name' => $task->project->name,
                'title' => $task->title,
                'description' => $task->description,
                'module' => $task->module,
                'type' => $task->type->value,
                'priority' => $task->priority->value,
                'status' => $task->status->value,
                'mode' => $task->mode->value,
                'current_role' => $task->current_role,
                'kanban_column' => $this->resolver->resolveColumn($task),
                'github_branch' => $task->github_branch,
                'github_pr_url' => $task->github_pr_url,
                'estimated_cost' => (float) $task->estimated_cost,
                'actual_cost' => (float) $task->actual_cost,
            ],
            'pipeline_steps' => $pipelineSteps,
            'hermes' => $this->hermesPresenter->detailBlock($task),
        ];
    }
}
