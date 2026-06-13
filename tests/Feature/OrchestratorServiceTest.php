<?php

namespace Tests\Feature;

use App\Enums\AgentRunStatus;
use App\Enums\AgentType;
use App\Enums\TaskMode;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\Task;
use App\Models\User;
use App\Services\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrchestratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(TaskType $type, TaskMode $mode): Task
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        foreach (AgentType::cases() as $agentType) {
            ProjectAgent::factory()->create([
                'project_id' => $project->id,
                'agent_type' => $agentType,
                'is_active' => true,
            ]);
        }

        return Task::factory()->create([
            'project_id' => $project->id,
            'type' => $type,
            'mode' => $mode,
            'status' => TaskStatus::InProgress,
        ]);
    }

    public function test_feature_manual_pipeline_starts_with_pm(): void
    {
        $task = $this->makeTask(TaskType::Feature, TaskMode::Manual);

        $orchestrator = app(OrchestratorService::class);
        $pipeline = $orchestrator->getPipelineForTask($task);

        $this->assertSame('pm', $pipeline[0]);
    }

    public function test_chore_pipeline_skips_pm(): void
    {
        $task = $this->makeTask(TaskType::Chore, TaskMode::FullAuto);

        $orchestrator = app(OrchestratorService::class);
        $pipeline = $orchestrator->getPipelineForTask($task);

        $this->assertSame('tech_lead', $pipeline[0]);
        $this->assertNotContains('pm', $pipeline);
    }

    public function test_full_auto_never_requires_gate(): void
    {
        $task = $this->makeTask(TaskType::Feature, TaskMode::FullAuto);
        $orchestrator = app(OrchestratorService::class);

        $this->assertFalse($orchestrator->requiresGate($task, 'ux'));
        $this->assertFalse($orchestrator->requiresGate($task, 'security'));
        $this->assertFalse($orchestrator->requiresGate($task, 'doc'));
    }

    public function test_semi_auto_only_requires_merge_gate(): void
    {
        $task = $this->makeTask(TaskType::Bug, TaskMode::SemiAuto);
        $orchestrator = app(OrchestratorService::class);

        $this->assertFalse($orchestrator->requiresGate($task, 'ux'));
        $this->assertFalse($orchestrator->requiresGate($task, 'security'));
        $this->assertTrue($orchestrator->requiresGate($task, 'doc'));
    }

    public function test_inactive_agents_are_skipped(): void
    {
        $task = $this->makeTask(TaskType::Feature, TaskMode::FullAuto);

        ProjectAgent::where('project_id', $task->project_id)
            ->where('agent_type', AgentType::Ux)
            ->update(['is_active' => false]);

        $orchestrator = app(OrchestratorService::class);

        AgentRun::create([
            'task_id' => $task->id,
            'agent_type' => AgentType::Pm,
            'status' => AgentRunStatus::Completed,
            'model' => 'claude-sonnet-4-6',
            'input' => [],
            'output' => 'spec done',
        ]);

        $next = $orchestrator->resolveNextAgent($task->fresh());

        $this->assertSame('tech_lead', $next);
    }
}
