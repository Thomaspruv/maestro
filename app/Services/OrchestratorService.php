<?php

namespace App\Services;

use App\Enums\AgentRunStatus;
use App\Enums\AgentType;
use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Enums\TaskMode;
use App\Enums\TaskStatus;
use App\Events\GatePending;
use App\Events\TaskCompleted;
use App\Jobs\ParallelAgentGroupJob;
use App\Jobs\RunAgentJob;
use App\Models\AgentRun;
use App\Models\Gate;
use App\Models\Task;

class OrchestratorService
{
    private const PARALLEL_GROUP = ['ux', 'tech_lead'];

    public function advance(Task $task): void
    {
        $task->loadMissing('project.agents');

        $nextAgent = $this->resolveNextAgent($task);

        if ($nextAgent === null) {
            $task->update([
                'status' => TaskStatus::Done,
                'current_agent' => null,
            ]);
            broadcast(new TaskCompleted($task->fresh()));

            return;
        }

        if ($this->requiresGate($task, $nextAgent)) {
            $lastRun = $task->agentRuns()->latest()->first();

            if ($lastRun) {
                $gate = Gate::create([
                    'task_id' => $task->id,
                    'agent_run_id' => $lastRun->id,
                    'gate_type' => $this->resolveGateType($nextAgent),
                    'status' => GateStatus::Pending,
                ]);

                broadcast(new GatePending($gate));
                app(NotificationService::class)->notifyGate($task, $gate);
            }

            return;
        }

        if (is_array($nextAgent)) {
            ParallelAgentGroupJob::dispatch($task, $nextAgent);

            return;
        }

        $queue = $nextAgent === AgentType::Dev->value ? 'dev-agent' : 'agents';
        RunAgentJob::dispatch($task, $nextAgent)->onQueue($queue);
    }

    /**
     * @return string|array<int, string>|null
     */
    public function resolveNextAgent(Task $task): string|array|null
    {
        $pipeline = $this->getPipelineForTask($task);
        $completed = $this->completedAgentTypes($task);

        $index = 0;

        while ($index < count($pipeline)) {
            $agent = $pipeline[$index];

            if (in_array($agent, $completed, true)) {
                $index++;

                continue;
            }

            if (! $this->isAgentActive($task, $agent)) {
                $this->markAgentSkipped($task, $agent);
                $completed[] = $agent;
                $index++;

                continue;
            }

            if ($this->isParallelGroupStart($pipeline, $index, $completed)) {
                $group = $this->resolveParallelGroup($task, $pipeline, $index, $completed);

                if (count($group) >= 2) {
                    return $group;
                }

                if (count($group) === 1) {
                    return $group[0];
                }

                $index += count(self::PARALLEL_GROUP);

                continue;
            }

            return $agent;
        }

        return null;
    }

    /**
     * @param  string|array<int, string>  $nextAgent
     */
    public function requiresGate(Task $task, string|array $nextAgent): bool
    {
        if ($task->mode === TaskMode::FullAuto) {
            return false;
        }

        $gateConfig = $this->getGateConfig($task);
        $agent = is_array($nextAgent) ? $nextAgent[0] : $nextAgent;

        if ($task->mode === TaskMode::SemiAuto) {
            return $agent === AgentType::Doc->value && ($gateConfig['gate_merge'] ?? true);
        }

        return match ($agent) {
            AgentType::Ux->value, AgentType::TechLead->value => $gateConfig['gate_specs'] ?? true,
            AgentType::Security->value => $gateConfig['gate_tech'] ?? true,
            AgentType::Doc->value => $gateConfig['gate_merge'] ?? true,
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public function getPipelineForTask(Task $task): array
    {
        $config = $task->project->pipeline_config ?? [];
        $type = $task->type->value;

        return $config[$type] ?? $this->defaultPipeline($type);
    }

    /**
     * @return array<int, string>
     */
    public function defaultPipeline(string $type): array
    {
        return config("maestro.default_pipelines.{$type}")
            ?? config('maestro.default_pipelines.feature', [
                'pm', 'ux', 'tech_lead', 'security', 'dev', 'qa', 'pr_expert', 'doc',
            ]);
    }

    /**
     * @return array<string, bool>
     */
    private function getGateConfig(Task $task): array
    {
        $gateConfig = $task->project->gate_config ?? [];

        return $gateConfig[$task->type->value] ?? config('maestro.default_gate_config.'.$task->type->value, []);
    }

    /**
     * @return array<int, string>
     */
    private function completedAgentTypes(Task $task): array
    {
        return $task->agentRuns()
            ->whereIn('status', [AgentRunStatus::Completed, AgentRunStatus::Skipped])
            ->pluck('agent_type')
            ->map(fn (AgentType $type) => $type->value)
            ->all();
    }

    private function isAgentActive(Task $task, string $agentType): bool
    {
        $projectAgent = $task->project->agents
            ->first(fn ($agent) => $agent->agent_type->value === $agentType);

        if ($projectAgent === null) {
            return true;
        }

        return $projectAgent->is_active;
    }

    private function markAgentSkipped(Task $task, string $agentType): void
    {
        AgentRun::create([
            'task_id' => $task->id,
            'agent_type' => $agentType,
            'status' => AgentRunStatus::Skipped,
            'model' => config('maestro.default_models.'.$agentType, 'claude-haiku-4-5'),
            'input' => [],
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $pipeline
     * @param  array<int, string>  $completed
     */
    private function isParallelGroupStart(array $pipeline, int $index, array $completed): bool
    {
        $agent = $pipeline[$index] ?? null;

        if ($agent !== AgentType::Ux->value) {
            return false;
        }

        $next = $pipeline[$index + 1] ?? null;

        return $next === AgentType::TechLead->value
            && ! in_array(AgentType::Ux->value, $completed, true)
            && ! in_array(AgentType::TechLead->value, $completed, true);
    }

    /**
     * @param  array<int, string>  $pipeline
     * @param  array<int, string>  $completed
     * @return array<int, string>
     */
    private function resolveParallelGroup(Task $task, array $pipeline, int $index, array $completed): array
    {
        $group = [];

        foreach (self::PARALLEL_GROUP as $agent) {
            if (! in_array($agent, $pipeline, true)) {
                continue;
            }

            if (in_array($agent, $completed, true)) {
                continue;
            }

            if (! $this->isAgentActive($task, $agent)) {
                $this->markAgentSkipped($task, $agent);

                continue;
            }

            $group[] = $agent;
        }

        return $group;
    }

    /**
     * @param  string|array<int, string>  $nextAgent
     */
    private function resolveGateType(string|array $nextAgent): GateType
    {
        $agent = is_array($nextAgent) ? $nextAgent[0] : $nextAgent;

        return match ($agent) {
            AgentType::Ux->value, AgentType::TechLead->value => GateType::SpecsReview,
            AgentType::Security->value => GateType::TechReview,
            AgentType::Doc->value => GateType::MergeReview,
            default => GateType::SpecsReview,
        };
    }
}
