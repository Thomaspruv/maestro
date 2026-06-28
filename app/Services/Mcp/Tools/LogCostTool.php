<?php

namespace App\Services\Mcp\Tools;

use App\Models\CostLog;
use App\Models\User;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\ResolvesMcpResources;

class LogCostTool implements McpTool
{
    use ResolvesMcpResources;

    public function name(): string
    {
        return 'log_cost';
    }

    public function description(): string
    {
        return 'Enregistre un coût LLM dans cost_logs.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer'],
                'task_id' => ['type' => 'integer'],
                'model' => ['type' => 'string'],
                'input_tokens' => ['type' => 'integer'],
                'output_tokens' => ['type' => 'integer'],
                'cost' => ['type' => 'number'],
            ],
            'required' => ['project_id', 'model', 'input_tokens', 'output_tokens', 'cost'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        foreach (['project_id', 'model', 'input_tokens', 'output_tokens', 'cost'] as $field) {
            if (! isset($arguments[$field])) {
                throw McpToolException::missing($field);
            }
        }

        $project = $this->findUserProject($user, (int) $arguments['project_id']);
        $task = null;

        if (! empty($arguments['task_id'])) {
            $task = $this->findUserTask($user, (int) $arguments['task_id']);
            if ($task->project_id !== $project->id) {
                throw McpToolException::invalid('task_id ne correspond pas au project_id.');
            }
        }

        $costLog = CostLog::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'pipeline_step_id' => null,
            'month' => now()->startOfMonth(),
            'input_tokens' => (int) $arguments['input_tokens'],
            'output_tokens' => (int) $arguments['output_tokens'],
            'cached_tokens' => 0,
            'cost' => (float) $arguments['cost'],
            'model' => $arguments['model'],
        ]);

        if ($task !== null) {
            $task->increment('actual_cost', (float) $arguments['cost']);
        }

        return [
            'cost_log' => [
                'id' => $costLog->id,
                'project_id' => $costLog->project_id,
                'task_id' => $costLog->task_id,
                'cost' => (float) $costLog->cost,
                'model' => $costLog->model,
            ],
        ];
    }
}
