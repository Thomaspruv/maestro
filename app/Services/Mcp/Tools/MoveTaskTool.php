<?php

namespace App\Services\Mcp\Tools;

use App\Models\User;
use App\Services\KanbanColumnResolver;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\ResolvesMcpResources;

class MoveTaskTool implements McpTool
{
    use ResolvesMcpResources;

    public function __construct(
        private readonly KanbanColumnResolver $resolver,
    ) {}

    public function name(): string
    {
        return 'move_task';
    }

    public function description(): string
    {
        return 'Déplace une tâche vers une colonne Kanban (recommandé pour le board).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer', 'description' => 'ID de la tâche'],
                'kanban_column' => [
                    'type' => 'string',
                    'description' => 'Slug de colonne Kanban',
                    'enum' => config('maestro.kanban_column_order', []),
                ],
            ],
            'required' => ['task_id', 'kanban_column'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        foreach (['task_id', 'kanban_column'] as $field) {
            if (! isset($arguments[$field])) {
                throw McpToolException::missing($field);
            }
        }

        $column = (string) $arguments['kanban_column'];

        if (! $this->resolver->isValidColumn($column)) {
            throw McpToolException::invalid('kanban_column invalide.');
        }

        $task = $this->findUserTask($user, (int) $arguments['task_id']);
        $this->resolver->applyColumn($task, $column);
        $task->refresh();

        return [
            'task' => [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'status' => $task->status->value,
                'current_role' => $task->current_role,
                'kanban_column' => $this->resolver->resolveColumn($task),
            ],
        ];
    }
}
