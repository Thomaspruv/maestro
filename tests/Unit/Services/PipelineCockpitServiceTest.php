<?php

namespace Tests\Unit\Services;

use App\Enums\AgentRunStatus;
use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Models\AgentRun;
use App\Models\Gate;
use App\Models\Project;
use App\Models\Task;
use App\Services\OrchestratorService;
use App\Services\PipelineCockpitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineCockpitServiceTest extends TestCase
{
    use RefreshDatabase;

    private PipelineCockpitService $service;

    private OrchestratorService $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orchestrator = $this->mock(OrchestratorService::class);
        $this->service = new PipelineCockpitService($this->orchestrator);
    }

    public function test_get_snapshot_with_pending_pipeline(): void
    {
        $task = Task::factory()->create(['status' => 'backlog']);
        $task->project->agents = collect(['agent_a', 'agent_b']);

        $this->orchestrator
            ->shouldReceive('getPipelineForTask')
            ->with($task)
            ->andReturn(['agent_a', 'agent_b']);

        $snapshot = $this->service->getSnapshot($task);

        $this->assertArrayHasKey('task_id', $snapshot);
        $this->assertArrayHasKey('steps', $snapshot);
        $this->assertEquals(0, $snapshot['total_cost']);
        $this->assertFalse($snapshot['is_active']);
    }

    public function test_get_snapshot_with_completed_agent(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['status' => 'in_progress']);
        $run = AgentRun::factory()
            ->for($task)
            ->create([
                'agent_type' => 'agent_a',
                'status' => AgentRunStatus::Completed,
                'cost' => 0.0123,
            ]);

        $this->orchestrator
            ->shouldReceive('getPipelineForTask')
            ->with($task)
            ->andReturn(['agent_a', 'agent_b']);

        $snapshot = $this->service->getSnapshot($task);

        // Pipeline has 2 agents: agent_a (completed) and agent_b (pending)
        $this->assertCount(2, $snapshot['steps']);
        $this->assertEquals('agent_a', $snapshot['steps'][0]['agent_type']);
        $this->assertEquals('completed', $snapshot['steps'][0]['status']);
        $this->assertEquals(0.0123, $snapshot['steps'][0]['cost']);
        $this->assertEquals('agent_b', $snapshot['steps'][1]['agent_type']);
        $this->assertEquals('pending', $snapshot['steps'][1]['status']);
        $this->assertEquals(0.0123, $snapshot['total_cost']);
    }

    public function test_get_snapshot_with_running_agent(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['status' => 'in_progress']);
        $run = AgentRun::factory()
            ->for($task)
            ->create([
                'agent_type' => 'agent_a',
                'status' => AgentRunStatus::Running,
                'cost' => 0.005,
            ]);

        $this->orchestrator
            ->shouldReceive('getPipelineForTask')
            ->with($task)
            ->andReturn(['agent_a', 'agent_b']);

        $snapshot = $this->service->getSnapshot($task);

        $this->assertTrue($snapshot['is_active']);
        $this->assertEquals('running', $snapshot['steps'][0]['status']);
    }

    public function test_get_snapshot_with_gate(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['status' => 'in_progress']);
        $run = AgentRun::factory()
            ->for($task)
            ->create([
                'agent_type' => 'specs_reviewer',
                'status' => AgentRunStatus::WaitingGate,
                'cost' => 0.01,
            ]);

        Gate::factory()
            ->for($task)
            ->for($run, 'agentRun')
            ->create([
                'gate_type' => GateType::SpecsReview,
                'status' => GateStatus::Pending,
            ]);

        $this->orchestrator
            ->shouldReceive('getPipelineForTask')
            ->with($task)
            ->andReturn(['specs_reviewer', 'tech_reviewer']);

        $snapshot = $this->service->getSnapshot($task);

        // Pipeline has: specs_reviewer (agent + gate) + tech_reviewer (agent in pending)
        $this->assertCount(3, $snapshot['steps']);
        $this->assertEquals('agent', $snapshot['steps'][0]['type']);
        $this->assertEquals('gate', $snapshot['steps'][1]['type']);
        $this->assertEquals('agent', $snapshot['steps'][2]['type']);
        $this->assertEquals('waiting_gate', $snapshot['steps'][0]['status']);
        $this->assertEquals('pending', $snapshot['steps'][1]['status']);
        $this->assertEquals('pending', $snapshot['steps'][2]['status']);
    }

    public function test_get_snapshot_with_failed_agent(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['status' => 'failed']);
        $run = AgentRun::factory()
            ->for($task)
            ->create([
                'agent_type' => 'agent_a',
                'status' => AgentRunStatus::Failed,
                'error_message' => 'Test error',
            ]);

        $this->orchestrator
            ->shouldReceive('getPipelineForTask')
            ->with($task)
            ->andReturn(['agent_a']);

        $snapshot = $this->service->getSnapshot($task);

        $this->assertEquals('blocked', $snapshot['steps'][0]['status']);
        $this->assertEquals('Test error', $snapshot['steps'][0]['error_message']);
    }

    public function test_get_snapshot_blocked_agent_timeout(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['status' => 'in_progress']);
        $run = AgentRun::factory()
            ->for($task)
            ->create([
                'agent_type' => 'agent_a',
                'status' => AgentRunStatus::Running,
                'updated_at' => now()->subMinutes(31),
            ]);

        $this->orchestrator
            ->shouldReceive('getPipelineForTask')
            ->with($task)
            ->andReturn(['agent_a']);

        $snapshot = $this->service->getSnapshot($task);

        $this->assertEquals('blocked', $snapshot['steps'][0]['status']);
    }

    public function test_get_snapshot_total_cost_aggregation(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['status' => 'in_progress']);

        AgentRun::factory()
            ->for($task)
            ->create([
                'agent_type' => 'agent_a',
                'status' => AgentRunStatus::Completed,
                'cost' => 0.01,
            ]);

        AgentRun::factory()
            ->for($task)
            ->create([
                'agent_type' => 'agent_b',
                'status' => AgentRunStatus::Completed,
                'cost' => 0.02,
            ]);

        AgentRun::factory()
            ->for($task)
            ->create([
                'agent_type' => 'agent_c',
                'status' => AgentRunStatus::Running,
                'cost' => 0.005,
            ]);

        $this->orchestrator
            ->shouldReceive('getPipelineForTask')
            ->with($task)
            ->andReturn(['agent_a', 'agent_b', 'agent_c']);

        $snapshot = $this->service->getSnapshot($task);

        // All three agents have costs: 0.01 + 0.02 + 0.005 = 0.035
        $this->assertEqualsWithDelta(0.035, $snapshot['total_cost'], 0.0001);
    }
}
