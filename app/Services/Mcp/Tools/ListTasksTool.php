<?php

namespace App\Services\Mcp\Tools;

use App\Models\User;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\ResolvesMcpResources;

class ListTasksTool implements McpTool
{
    use ResolvesMcpResources;

    public function name(): string
    {
        return 'list_tasks';
    }

    public function description(): string
    {
        return 'Liste les tâches d\'un projet, optionnellement filtrées par statut.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'ID du projet'],
                'status' => ['type' => 'string', 'description' => 'Statut de tâche (backlog, in_progress, waiting_hermes, in_review, done, failed)'],
            ],
            'required' => ['project_id'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        if (! isset($arguments['project_id'])) {
            throw McpToolException::missing('project_id');
        }

        $project = $this->findUserProject($user, (int) $arguments['project_id']);

        $query = $project->tasks()->orderByDesc('updated_at');

        if (! empty($arguments['status'])) {
            $query->where('status', $arguments['status']);
        }

        $tasks = $query->get([
            'id', 'uuid', 'title', 'type', 'priority', 'status', 'mode',
            'current_role', 'module', 'estimated_cost', 'actual_cost',
        ]);

        return [
            'tasks' => $tasks->map(fn ($task) => [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'type' => $task->type->value,
                'priority' => $task->priority->value,
                'status' => $task->status->value,
                'mode' => $task->mode->value,
                'current_role' => $task->current_role,
                'module' => $task->module,
                'estimated_cost' => (float) $task->estimated_cost,
                'actual_cost' => (float) $task->actual_cost,
            ])->values()->all(),
        ];
    }
}
