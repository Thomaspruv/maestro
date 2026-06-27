<?php

namespace App\Services\Mcp\Tools;

use App\Enums\TaskMode;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\User;
use App\Services\CostEstimatorService;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\ResolvesMcpResources;

class CreateTaskTool implements McpTool
{
    use ResolvesMcpResources;

    public function __construct(
        private readonly CostEstimatorService $estimator,
    ) {}

    public function name(): string
    {
        return 'create_task';
    }

    public function description(): string
    {
        return 'Crée une tâche dans un projet Maestro.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'type' => ['type' => 'string', 'enum' => ['feature', 'bug', 'improvement', 'chore']],
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                'module' => ['type' => 'string'],
            ],
            'required' => ['project_id', 'title', 'type', 'priority'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        foreach (['project_id', 'title', 'type', 'priority'] as $field) {
            if (! isset($arguments[$field]) || $arguments[$field] === '') {
                throw McpToolException::missing($field);
            }
        }

        $project = $this->findUserProject($user, (int) $arguments['project_id']);

        $type = TaskType::tryFrom($arguments['type']);
        $priority = TaskPriority::tryFrom($arguments['priority']);

        if ($type === null || $priority === null) {
            throw McpToolException::invalid('type ou priority invalide.');
        }

        $defaultModes = $project->default_modes ?? config('maestro.default_modes', []);
        $modeValue = $defaultModes[$type->value] ?? 'manual';
        $mode = TaskMode::tryFrom($modeValue) ?? TaskMode::Manual;

        $task = $project->tasks()->create([
            'title' => $arguments['title'],
            'description' => $arguments['description'] ?? null,
            'module' => $arguments['module'] ?? null,
            'type' => $type,
            'priority' => $priority,
            'mode' => $mode,
            'status' => TaskStatus::Backlog,
        ]);

        $estimate = $this->estimator->estimate($task);
        $task->update(['estimated_cost' => $estimate['total_mid']]);

        return [
            'task' => [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'status' => $task->status->value,
                'estimated_cost' => (float) $task->estimated_cost,
            ],
        ];
    }
}
