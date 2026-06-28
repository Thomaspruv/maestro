<?php

namespace App\Services\Mcp\Tools;

use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Events\GatePending;
use App\Models\Gate;
use App\Models\User;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\ResolvesMcpResources;

class RequestGateTool implements McpTool
{
    use ResolvesMcpResources;

    /**
     * @var array<string, GateType>
     */
    private const GATE_TYPE_MAP = [
        'gate_specs' => GateType::SpecsReview,
        'gate_tech' => GateType::TechReview,
        'gate_merge' => GateType::MergeReview,
        'specs_review' => GateType::SpecsReview,
        'tech_review' => GateType::TechReview,
        'merge_review' => GateType::MergeReview,
    ];

    public function name(): string
    {
        return 'request_gate';
    }

    public function description(): string
    {
        return 'Crée une gate d\'approbation humaine pour une tâche.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer'],
                'pipeline_step_id' => ['type' => 'integer'],
                'gate_type' => ['type' => 'string', 'enum' => ['gate_specs', 'gate_tech', 'gate_merge', 'specs_review', 'tech_review', 'merge_review']],
            ],
            'required' => ['task_id', 'pipeline_step_id', 'gate_type'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        foreach (['task_id', 'pipeline_step_id', 'gate_type'] as $field) {
            if (! isset($arguments[$field])) {
                throw McpToolException::missing($field);
            }
        }

        $task = $this->findUserTask($user, (int) $arguments['task_id']);
        $gateType = self::GATE_TYPE_MAP[$arguments['gate_type']] ?? null;

        if ($gateType === null) {
            throw McpToolException::invalid('gate_type invalide.');
        }

        $run = $task->pipelineSteps()->whereKey((int) $arguments['pipeline_step_id'])->first();

        if ($run === null) {
            throw McpToolException::notFound('agent_run');
        }

        $gate = Gate::create([
            'task_id' => $task->id,
            'pipeline_step_id' => $run->id,
            'gate_type' => $gateType,
            'status' => GateStatus::Pending,
        ]);

        broadcast(new GatePending($gate));

        return [
            'gate' => [
                'id' => $gate->id,
                'gate_type' => $gate->gate_type->value,
                'status' => $gate->status->value,
                'task_id' => $gate->task_id,
            ],
        ];
    }
}
