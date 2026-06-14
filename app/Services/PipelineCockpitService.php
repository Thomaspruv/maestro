<?php

namespace App\Services;

use App\Enums\AgentRunStatus;
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
        $task->loadMissing(['project.agents', 'agentRuns', 'gates']);

        $pipeline = $this->orchestrator->getPipelineForTask($task);
        $completedAgents = $this->getCompletedAgentTypes($task);
        $runsByAgent = $task->agentRuns->keyBy('agent_type');
        $gatesByAgentRun = $task->gates->keyBy('agent_run_id');

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
            if ($run && $run->status === AgentRunStatus::Running) {
                $isActive = true;
            }

            // Gate step (if agent is completed and gate exists)
            if ($run && in_array($run->status, [
                AgentRunStatus::Completed,
                AgentRunStatus::WaitingGate,
            ])) {
                $gate = $gatesByAgentRun->get($run->id);

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
                'agent_type' => $agentType,
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
            'agent_type' => $agentType,
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
    private function getCompletedAgentTypes(Task $task): array
    {
        return $task->agentRuns()
            ->whereIn('status', [AgentRunStatus::Completed, AgentRunStatus::Skipped])
            ->pluck('agent_type')
            ->all();
    }

    private function mapAgentStatus(AgentRunStatus $status): string
    {
        return match ($status) {
            AgentRunStatus::Pending => 'pending',
            AgentRunStatus::Running => 'running',
            AgentRunStatus::Completed => 'completed',
            AgentRunStatus::Failed => 'blocked',
            AgentRunStatus::WaitingGate => 'waiting_gate',
            AgentRunStatus::Skipped => 'skipped',
        };
    }
}
