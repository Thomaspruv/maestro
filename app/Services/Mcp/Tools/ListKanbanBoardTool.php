<?php

namespace App\Services\Mcp\Tools;

use App\Models\User;
use App\Services\KanbanColumnResolver;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\ResolvesMcpResources;

class ListKanbanBoardTool implements McpTool
{
    use ResolvesMcpResources;

    public function __construct(
        private readonly KanbanColumnResolver $resolver,
    ) {}

    public function name(): string
    {
        return 'list_kanban_board';
    }

    public function description(): string
    {
        return 'Retourne le tableau Kanban complet d\'un projet, groupé par colonne rôle.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'ID du projet'],
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
        $tasks = $project->tasks()->orderBy('sort_order')->get();
        $grouped = $this->resolver->groupTasksByColumn($tasks);

        $columns = collect($this->resolver->columns())
            ->map(fn (array $column) => [
                'slug' => $column['slug'],
                'label' => $column['label'],
                'tasks' => ($grouped[$column['slug']] ?? collect())
                    ->map(fn ($task) => $this->resolver->taskSummary($task))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return [
            'columns' => $columns,
            'column_order' => $this->resolver->columnOrder(),
        ];
    }
}
