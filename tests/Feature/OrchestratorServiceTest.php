<?php

namespace Tests\Feature;

use App\Pipeline\RunnerFactory;
use App\Enums\PipelineStepStatus;
use App\Enums\GateStatus;
use App\Enums\TaskMode;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\PipelineStep;
use App\Models\Gate;
use App\Models\Project;
use App\Models\ProjectRole;
use App\Models\Task;
use App\Models\User;
use App\Models\PipelineRole;
use App\Services\GateReviewService;
use App\Services\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrchestratorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['maestro.internal_pipeline_enabled' => true]);
    }

    private function makeTask(TaskType $type, TaskMode $mode): Task
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        foreach ($user->pipelineRoles as $pipelineRole) {
            ProjectRole::factory()->create([
                'project_id' => $project->id,
                'role' => $pipelineRole->slug,
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

        ProjectRole::where('project_id', $task->project_id)
            ->where('role', 'ux')
            ->update(['is_active' => false]);

        $orchestrator = app(OrchestratorService::class);

        PipelineStep::create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Completed,
            'model' => 'claude-sonnet-4-6',
            'input' => [],
            'output' => 'spec done',
        ]);

        $next = $orchestrator->resolveNextRole($task->fresh());

        $this->assertSame('tech_lead', $next);
    }

    public function test_approving_gate_dispatches_next_agent_instead_of_recreating_gate(): void
    {
        Queue::fake();

        $task = $this->makeTask(TaskType::Feature, TaskMode::Manual);

        PipelineStep::create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Completed,
            'model' => 'claude-sonnet-4-6',
            'input' => [],
            'output' => 'spec done',
        ]);

        $gate = Gate::factory()->create([
            'task_id' => $task->id,
            'pipeline_step_id' => $task->pipelineSteps()->first()->id,
            'status' => GateStatus::Pending,
        ]);

        app(GateReviewService::class)->approve($gate);

        $this->assertSame(GateStatus::Approved, $gate->fresh()->status);
        $this->assertDatabaseMissing('gates', [
            'task_id' => $task->id,
            'status' => GateStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('pipeline_steps', [
            'task_id' => $task->id,
            'role' => 'ux',
            'status' => PipelineStepStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('pipeline_steps', [
            'task_id' => $task->id,
            'role' => 'tech_lead',
            'status' => PipelineStepStatus::Pending->value,
        ]);
    }

    public function test_advance_creates_pending_agent_run_before_job(): void
    {
        Queue::fake();

        $task = $this->makeTask(TaskType::Feature, TaskMode::FullAuto);

        app(OrchestratorService::class)->advance($task->fresh());

        $this->assertDatabaseHas('pipeline_steps', [
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Pending->value,
        ]);

        $this->assertSame('pm', $task->fresh()->current_role);
    }

    public function test_custom_agent_in_pipeline_resolves_without_enum_error(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'pipeline_config' => [
                'feature' => ['pm', 'legal_reviewer', 'dev'],
            ],
        ]);

        PipelineRole::factory()->create([
            'user_id' => $user->id,
            'slug' => 'legal_reviewer',
            'name' => 'Legal',
        ]);

        ProjectRole::factory()->create([
            'project_id' => $project->id,
            'role' => 'pm',
            'is_active' => true,
        ]);

        ProjectRole::factory()->create([
            'project_id' => $project->id,
            'role' => 'legal_reviewer',
            'is_active' => true,
        ]);

        ProjectRole::factory()->create([
            'project_id' => $project->id,
            'role' => 'dev',
            'is_active' => true,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'type' => TaskType::Feature,
            'mode' => TaskMode::FullAuto,
            'status' => TaskStatus::InProgress,
        ]);

        PipelineStep::create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Completed,
            'model' => 'claude-sonnet-4-6',
            'input' => [],
            'output' => 'spec',
        ]);

        $orchestrator = app(OrchestratorService::class);
        $next = $orchestrator->resolveNextRole($task->fresh());

        $this->assertSame('legal_reviewer', $next);

        $agent = RunnerFactory::make('legal_reviewer', $project);
        $this->assertNotEmpty($agent->systemPrompt());
    }
}
