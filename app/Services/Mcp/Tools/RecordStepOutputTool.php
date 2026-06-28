<?php

namespace App\Services\Mcp\Tools;

use App\Enums\PipelineStepStatus;
use App\Enums\TaskStatus;
use App\Models\CostLog;
use App\Models\PipelineStep;
use App\Models\User;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\ResolvesMcpResources;
use App\Services\OrchestratorService;
use App\Services\PipelineStepRunnerService;

class RecordStepOutputTool implements McpTool
{
    use ResolvesMcpResources;

    public function __construct(
        private readonly PipelineStepRunnerService $runner,
        private readonly OrchestratorService $orchestrator,
    ) {}

    public function name(): string
    {
        return 'record_step_output';
    }

    public function description(): string
    {
        return 'Enregistre l\'output d\'une étape Hermes (crée un PipelineStep terminé).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer'],
                'role' => ['type' => 'string'],
                'output' => ['type' => 'string'],
                'model' => ['type' => 'string'],
                'input_tokens' => ['type' => 'integer'],
                'output_tokens' => ['type' => 'integer'],
                'cost' => ['type' => 'number'],
            ],
            'required' => ['task_id', 'role', 'output', 'model'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        foreach (['task_id', 'role', 'output', 'model'] as $field) {
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

        $step = PipelineStep::create([
            'task_id' => $task->id,
            'role' => $arguments['role'],
            'status' => PipelineStepStatus::Completed,
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
                'pipeline_step_id' => $step->id,
                'month' => now()->startOfMonth(),
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cached_tokens' => 0,
                'cost' => $cost,
                'model' => $model,
            ]);
        }

        $task->increment('actual_cost', $cost);

        if ($arguments['role'] === 'dev') {
            $task->update([
                'status' => TaskStatus::InProgress,
                'current_role' => null,
            ]);
            $this->orchestrator->advance($task->fresh());
        }

        return [
            'pipeline_step' => [
                'id' => $step->id,
                'role' => $step->role,
                'status' => $step->status->value,
                'cost' => (float) $step->cost,
            ],
            'task' => [
                'id' => $task->id,
                'status' => $task->fresh()->status->value,
                'current_role' => $task->fresh()->current_role,
            ],
        ];
    }
}
