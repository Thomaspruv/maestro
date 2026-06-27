<?php

namespace Tests\Unit\Services;

use App\Enums\AgentRunStatus;
use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Models\Gate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\OrchestratorService;
use App\Services\PipelineCockpitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineCockpitServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_empty_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $service = new PipelineCockpitService(app(OrchestratorService::class));

        $snapshot = $service->getSnapshot($task);

        $this->assertEquals($task->id, $snapshot['task_id']);
        $this->assertGreaterThanOrEqual(0, count($snapshot['steps']));
        $this->assertEquals(0, $snapshot['total_cost']);
        $this->assertFalse($snapshot['is_active']);
    }

    public function test_snapshot_with_completed_agent(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $task->agentRuns()->create([
            'agent_type' => 'pm',
            'status' => AgentRunStatus::Completed,
            'cost' => 0.1234,
            'input' => [],
            'output' => 'Some output',
            'model' => 'claude-opus-4-8',
        ]);

        $service = new PipelineCockpitService(app(OrchestratorService::class));
        $snapshot = $service->getSnapshot($task);

        $pmStep = collect($snapshot['steps'])->first(fn ($s) => $s['agent_type'] === 'pm');
        $this->assertNotNull($pmStep);
        $this->assertEquals('agent', $pmStep['type']);
        $this->assertEquals('completed', $pmStep['status']);
        $this->assertEquals(0.1234, $pmStep['cost']);
        $this->assertEquals(0.1234, $snapshot['total_cost']);
    }

    public function test_snapshot_with_pending_gate(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $run = $task->agentRuns()->create([
            'agent_type' => 'pm',
            'status' => AgentRunStatus::WaitingGate,
            'input' => [],
            'output' => 'Output',
            'model' => 'claude-opus-4-8',
        ]);

        Gate::create([
            'task_id' => $task->id,
            'agent_run_id' => $run->id,
            'gate_type' => GateType::SpecsReview,
            'status' => GateStatus::Pending,
        ]);

        $service = new PipelineCockpitService(app(OrchestratorService::class));
        $snapshot = $service->getSnapshot($task);

        $gateStep = collect($snapshot['steps'])->first(fn ($s) => $s['type'] === 'gate');
        $this->assertNotNull($gateStep);
        $this->assertEquals('specs_review', $gateStep['gate_type']);
        $this->assertEquals('pending', $gateStep['status']);
    }

    public function test_snapshot_marks_stale_running_as_blocked(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $run = $task->agentRuns()->create([
            'agent_type' => 'pm',
            'status' => AgentRunStatus::Running,
            'input' => [],
            'model' => 'claude-opus-4-8',
        ]);
        $run->forceFill(['updated_at' => now()->subMinutes(35)])->saveQuietly();

        $service = new PipelineCockpitService(app(OrchestratorService::class));
        $snapshot = $service->getSnapshot($task);

        $pmStep = collect($snapshot['steps'])->first(fn ($s) => ($s['agent_type'] ?? null) === 'pm');
        $this->assertNotNull($pmStep);
        $this->assertEquals('blocked', $pmStep['status']);
    }
}
