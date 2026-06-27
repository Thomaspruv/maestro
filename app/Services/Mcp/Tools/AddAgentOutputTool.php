<?php

namespace App\Services\Mcp\Tools;

use App\Enums\AgentRunStatus;
use App\Enums\TaskStatus;
use App\Models\AgentRun;
use App\Models\CostLog;
use App\Models\User;
use App\Services\AgentRunnerService;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\ResolvesMcpResources;
use App\Services\OrchestratorService;

class AddAgentOutputTool implements McpTool
{
    use ResolvesMcpResources;

    public function __construct(
        private readonly AgentRunnerService $runner,
        private readonly OrchestratorService $orchestrator,
    ) {}

    public function name(): string
    {
        return 'add_agent_output';
    }

    public function description(): string
    {
        return 'Enregistre l\'output d\'un agent Hermes (crée un AgentRun terminé).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer'],
                'agent_type' => ['type' => 'string'],
                'output' => ['type' => 'string'],
                'model' => ['type' => 'string'],
                'input_tokens' => ['type' => 'integer'],
                'output_tokens' => ['type' => 'integer'],
                'cost' => ['type' => 'number'],
            ],
            'required' => ['task_id', 'agent_type', 'output', 'model'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        foreach (['task_id', 'agent_type', 'output', 'model'] as $field) {
            if (! isset($arguments[$field])) {
                throw McpToolException::missing($field);
            }
        }

        $task = $this->findUserTask($user, (int) $arguments['task_id']);
        $task->loadMissing('project');

        $inputTokens = (int) ($arguments['input_tokens'] ?? 0);
        $outputTokens = (int) ($arguments['output_tokens'] ?? 0);
        $model = $arguments['model'];
        $cost = isset($arguments['cost'])
            ? (float) $arguments['cost']
            : $this->runner->calculateCost($model, $inputTokens, $outputTokens, 0);

        $run = AgentRun::create([
            'task_id' => $task->id,
            'agent_type' => $arguments['agent_type'],
            'status' => AgentRunStatus::Completed,
            'input' => [],
            'output' => $arguments['output'],
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_tokens' => 0,
            'cost' => $cost,
            'attempt' => 1,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        if ($cost > 0) {
            CostLog::create([
                'user_id' => $task->project->user_id,
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'agent_run_id' => $run->id,
                'month' => now()->startOfMonth(),
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cached_tokens' => 0,
                'cost' => $cost,
                'model' => $model,
            ]);
        }

        $task->increment('actual_cost', $cost);

        if ($arguments['agent_type'] === 'dev') {
            $task->update([
                'status' => TaskStatus::InProgress,
                'current_agent' => null,
            ]);
            $this->orchestrator->advance($task->fresh());
        }

        return [
            'agent_run' => [
                'id' => $run->id,
                'agent_type' => $run->agent_type,
                'status' => $run->status->value,
                'cost' => (float) $run->cost,
            ],
            'task' => [
                'id' => $task->id,
                'status' => $task->fresh()->status->value,
                'current_agent' => $task->fresh()->current_agent,
            ],
        ];
    }
}
