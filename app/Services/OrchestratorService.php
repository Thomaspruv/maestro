<?php

namespace App\Services;

use App\Enums\PipelineStepStatus;
use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Enums\TaskMode;
use App\Enums\TaskStatus;
use App\Events\PipelineStepUpdated;
use App\Events\GatePending;
use App\Events\TaskCompleted;
use App\Jobs\ParallelPipelineStepGroupJob;
use App\Jobs\RunPipelineStepJob;
use App\Models\PipelineStep;
use App\Models\Gate;
use App\Models\Task;

class OrchestratorService
{
    private const PARALLEL_GROUP = ['ux', 'tech_lead'];

    public function advance(Task $task, bool $afterGateApproval = false): void
    {
        $task->loadMissing('project.roles');

        $nextAgent = $this->resolveNextRole($task);

        if ($nextAgent === null) {
            $task->update([
                'status' => TaskStatus::Done,
                'current_role' => null,
            ]);
            broadcast(new TaskCompleted($task->fresh()));

            return;
        }

        if (! $afterGateApproval && $this->requiresGate($task, $nextAgent)) {
            if ($task->gates()->where('status', GateStatus::Pending)->exists()) {
                return;
            }

            $lastRun = $task->pipelineSteps()->latest()->first();

            if ($lastRun) {
                $gate = Gate::create([
                    'task_id' => $task->id,
                    'pipeline_step_id' => $lastRun->id,
                    'gate_type' => $this->resolveGateType($nextAgent),
                    'status' => GateStatus::Pending,
                ]);

                broadcast(new GatePending($gate));
                app(NotificationService::class)->notifyGate($task, $gate);
            }

            return;
        }

        if (is_array($nextAgent)) {
            $this->dispatchParallelGroup($task, $nextAgent);

            return;
        }

        if ($this->requiresHermesHandoff($task, $nextAgent)) {
            $task->update([
                'status' => TaskStatus::WaitingHermes,
                'current_role' => 'hermes',
            ]);

            return;
        }

        $this->dispatchPipelineStep($task, $nextAgent);
    }

    public function dispatchPipelineStep(Task $task, string $agentType, bool $skipAdvance = false): PipelineStep
    {
        $run = PipelineStep::create([
            'task_id' => $task->id,
            'role' => $agentType,
            'status' => PipelineStepStatus::Pending,
            'input' => [],
            'model' => $this->resolveModelForRole($task, $agentType),
            'attempt' => 1,
        ]);

        $task->update([
            'status' => TaskStatus::InProgress,
            'current_role' => $agentType,
        ]);

        broadcast(new PipelineStepUpdated($run->fresh()));

        $queue = PipelineRoleCapabilities::queue($agentType);
        RunPipelineStepJob::dispatch(
            $task,
            $agentType,
            skipAdvance: $skipAdvance,
            pipelineStepId: $run->id,
        )->onQueue($queue);

        return $run;
    }

    /**
     * @param  array<int, string>  $agentTypes
     */
    private function dispatchParallelGroup(Task $task, array $agentTypes): void
    {
        $runIds = [];

        foreach ($agentTypes as $agentType) {
            $run = PipelineStep::create([
                'task_id' => $task->id,
                'role' => $agentType,
                'status' => PipelineStepStatus::Pending,
                'input' => [],
                'model' => $this->resolveModelForRole($task, $agentType),
                'attempt' => 1,
            ]);

            $runIds[$agentType] = $run->id;
            broadcast(new PipelineStepUpdated($run->fresh()));
        }

        $task->update([
            'status' => TaskStatus::InProgress,
            'current_role' => $agentTypes[0],
        ]);

        ParallelPipelineStepGroupJob::dispatch($task, $agentTypes, $runIds);
    }

    private function resolveModelForRole(Task $task, string $agentType): string
    {
        $task->loadMissing('project');

        return PipelineRoleCapabilities::resolveModel($agentType, $task->project);
    }

    /**
     * @return string|array<int, string>|null
     */
    public function resolveNextRole(Task $task): string|array|null
    {
        $pipeline = $this->getPipelineForTask($task);
        $completed = $this->completedPipelineRoleSlugs($task);

        $index = 0;

        while ($index < count($pipeline)) {
            $agent = $pipeline[$index];

            if (in_array($agent, $completed, true)) {
                $index++;

                continue;
            }

            if (! $this->isRoleActive($task, $agent)) {
                $this->markRoleSkipped($task, $agent);
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
            return $agent === 'doc' && ($gateConfig['gate_merge'] ?? true);
        }

        return match ($agent) {
            'ux', 'tech_lead' => $gateConfig['gate_specs'] ?? true,
            'security' => $gateConfig['gate_tech'] ?? true,
            'doc' => $gateConfig['gate_merge'] ?? true,
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

        $pipeline = $config[$type] ?? $this->defaultPipeline($type);

        return array_values(array_filter(
            $pipeline,
            fn (string $agent) => $agent !== 'dev',
        ));
    }

    /**
     * @return array<int, string>
     */
    public function defaultPipeline(string $type): array
    {
        return config("maestro.default_pipelines.{$type}")
            ?? config('maestro.default_pipelines.feature', [
                'pm', 'ux', 'tech_lead', 'security', 'qa', 'pr_expert', 'doc',
            ]);
    }

    private function requiresHermesHandoff(Task $task, string $nextAgent): bool
    {
        if ($nextAgent !== 'qa') {
            return false;
        }

        return ! $task->pipelineSteps()
            ->where('role', 'dev')
            ->where('status', PipelineStepStatus::Completed)
            ->exists();
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
    private function completedPipelineRoleSlugs(Task $task): array
    {
        return $task->pipelineSteps()
            ->whereIn('status', [PipelineStepStatus::Completed, PipelineStepStatus::Skipped])
            ->pluck('role')
            ->all();
    }

    private function isRoleActive(Task $task, string $agentType): bool
    {
        $projectRole = $task->project->roles
            ->first(fn ($agent) => $agent->role === $agentType);

        if ($projectRole === null) {
            return true;
        }

        return $projectRole->is_active;
    }

    private function markRoleSkipped(Task $task, string $agentType): void
    {
        PipelineStep::create([
            'task_id' => $task->id,
            'role' => $agentType,
            'status' => PipelineStepStatus::Skipped,
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

        if ($agent !== 'ux') {
            return false;
        }

        $next = $pipeline[$index + 1] ?? null;

        return $next === 'tech_lead'
            && ! in_array('ux', $completed, true)
            && ! in_array('tech_lead', $completed, true);
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

            if (! $this->isRoleActive($task, $agent)) {
                $this->markRoleSkipped($task, $agent);

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
            'ux', 'tech_lead' => GateType::SpecsReview,
            'security' => GateType::TechReview,
            'doc' => GateType::MergeReview,
            default => GateType::SpecsReview,
        };
    }
}
