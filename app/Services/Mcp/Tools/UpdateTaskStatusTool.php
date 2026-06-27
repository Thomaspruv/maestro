<?php

namespace App\Services\Mcp\Tools;

use App\Enums\TaskStatus;
use App\Models\User;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\ResolvesMcpResources;

class UpdateTaskStatusTool implements McpTool
{
    use ResolvesMcpResources;

    public function name(): string
    {
        return 'update_task_status';
    }

    public function description(): string
    {
        return 'Met à jour le statut d\'une tâche.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer'],
                'status' => ['type' => 'string', 'enum' => ['backlog', 'in_progress', 'waiting_hermes', 'in_review', 'done', 'failed']],
            ],
            'required' => ['task_id', 'status'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        foreach (['task_id', 'status'] as $field) {
            if (! isset($arguments[$field])) {
                throw McpToolException::missing($field);
            }
        }

        $status = TaskStatus::tryFrom($arguments['status']);

        if ($status === null) {
            throw McpToolException::invalid('status invalide.');
        }

        $task = $this->findUserTask($user, (int) $arguments['task_id']);
        $task->update(['status' => $status]);

        return [
            'task' => [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'status' => $task->status->value,
            ],
        ];
    }
}
