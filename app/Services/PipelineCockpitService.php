<?php

namespace App\Services;

use App\Enums\PipelineStepStatus;
use App\Enums\GateStatus;
use App\Models\Gate;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PipelineCockpitService
{
    public function __construct(
        private OrchestratorService $orchestrator,
    ) {}

    /**
     * Build a complete cockpit snapshot: pipeline sequence with agents and gates.
     *
     * @return array<string, mixed>
     */
    public function getSnapshot(Task $task): array
    {
        $task->loadMissing(['project.roles', 'pipelineSteps', 'gates']);

        $pipeline = $this->orchestrator->getPipelineForTask($task);
        $completedAgents = $this->getCompletedPipelineRoleSlugs($task);
        $runsByAgent = $task->pipelineSteps->keyBy('role');
        $gatesByPipelineStep = $task->gates->keyBy('pipeline_step_id');

        $steps = [];
        $totalCost = 0;
        $isActive = false;

        foreach ($pipeline as $agentType) {
            $run = $runsByAgent->get($agentType);

            // Agent step
            $agentStep = $this->buildAgentStep($agentType, $run, $task);
            $steps[] = $agentStep;

            if ($run?->cost) {
                $totalCost += $run->cost;
            }

            // Check if this agent is running
            if ($run && $run->status === PipelineStepStatus::Running) {
                $isActive = true;
            }

            // Gate step (if agent is completed and gate exists)
            if ($run && in_array($run->status, [
                PipelineStepStatus::Completed,
                PipelineStepStatus::WaitingGate,
            ])) {
                $gate = $gatesByPipelineStep->get($run->id);

                if ($gate) {
                    $gateStep = $this->buildGateStep($gate);
                    $steps[] = $gateStep;
                }
            }
        }

        return [
            'task_id' => $task->id,
            'task_uuid' => $task->uuid,
            'status' => $task->status->value,
            'steps' => $steps,
            'total_cost' => $totalCost,
            'is_active' => $isActive,
            'completed_agents' => $completedAgents,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAgentStep(string $agentType, ?object $run, Task $task): array
    {
        if (! $run) {
            return [
                'type' => 'agent',
                'role' => $agentType,
                'status' => 'pending',
                'cost' => null,
                'run_id' => null,
            ];
        }

        $status = $this->mapAgentStatus($run->status);

        // Guard: if status is 'running' but run was last updated > 30min ago, mark as blocked
        if ($status === 'running' && $run->updated_at && $run->updated_at->diffInMinutes(now()) > 30) {
            $status = 'blocked';
        }

        return [
            'type' => 'agent',
            'role' => $agentType,
            'status' => $status,
            'cost' => $run->cost ? (float) $run->cost : null,
            'run_id' => $run->id,
            'output_exists' => ! is_null($run->output),
            'attempt' => $run->attempt,
            'error_message' => $run->error_message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGateStep(Gate $gate): array
    {
        return [
            'type' => 'gate',
            'gate_type' => $gate->gate_type->value,
            'status' => match ($gate->status) {
                GateStatus::Pending => 'pending',
                GateStatus::Approved => 'approved',
                GateStatus::Rejected => 'rejected',
            },
            'gate_id' => $gate->id,
            'feedback' => $gate->feedback,
            'regeneration_count' => $gate->regeneration_count,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getCompletedPipelineRoleSlugs(Task $task): array
    {
        return $task->pipelineSteps()
            ->whereIn('status', [PipelineStepStatus::Completed, PipelineStepStatus::Skipped])
            ->pluck('role')
            ->all();
    }

    private function mapAgentStatus(PipelineStepStatus $status): string
    {
        return match ($status) {
            PipelineStepStatus::Pending => 'pending',
            PipelineStepStatus::Running => 'running',
            PipelineStepStatus::Completed => 'completed',
            PipelineStepStatus::Failed => 'blocked',
            PipelineStepStatus::WaitingGate => 'waiting_gate',
            PipelineStepStatus::Skipped => 'skipped',
        };
    }
}
