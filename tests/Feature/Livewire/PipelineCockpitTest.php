<?php

namespace Tests\Feature\Livewire;

use App\Enums\AgentRunStatus;
use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Livewire\PipelineCockpit;
use App\Models\AgentRun;
use App\Models\Gate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PipelineCockpitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user, 'owner')->create();
        $this->task = Task::factory()->for($this->project)->create(['status' => 'in_progress']);
    }

    public function test_component_renders_initial_snapshot(): void
    {
        $this->actingAs($this->user);

        Livewire::test(PipelineCockpit::class, ['task' => $this->task])
            ->assertViewHas('shouldPoll');
    }

    public function test_component_requires_authorization(): void
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        Livewire::test(PipelineCockpit::class, ['task' => $this->task])
            ->assertForbidden();
    }

    public function test_refresh_snapshot_updates_display(): void
    {
        $this->actingAs($this->user);

        AgentRun::factory()
            ->for($this->task)
            ->create([
                'agent_type' => 'specs_reviewer',
                'status' => AgentRunStatus::Running,
            ]);

        Livewire::test(PipelineCockpit::class, ['task' => $this->task])
            ->call('refreshSnapshot')
            ->assertViewHas('snapshot', fn ($snapshot) => count($snapshot['steps']) > 0);
    }

    public function test_approve_gate_action(): void
    {
        $this->actingAs($this->user);

        $run = AgentRun::factory()
            ->for($this->task)
            ->create([
                'agent_type' => 'specs_reviewer',
                'status' => AgentRunStatus::WaitingGate,
            ]);

        $gate = Gate::factory()
            ->for($this->task)
            ->for($run, 'agentRun')
            ->create([
                'gate_type' => GateType::SpecsReview,
                'status' => GateStatus::Pending,
            ]);

        Livewire::test(PipelineCockpit::class, ['task' => $this->task])
            ->call('approveGate', $gate->id)
            ->assertDispatched('gate-reviewed');

        $this->assertEquals(GateStatus::Approved, $gate->fresh()->status);
    }

    public function test_reject_gate_action(): void
    {
        $this->actingAs($this->user);

        $run = AgentRun::factory()
            ->for($this->task)
            ->create([
                'agent_type' => 'tech_reviewer',
                'status' => AgentRunStatus::WaitingGate,
            ]);

        $gate = Gate::factory()
            ->for($this->task)
            ->for($run, 'agentRun')
            ->create([
                'gate_type' => GateType::TechReview,
                'status' => GateStatus::Pending,
            ]);

        Livewire::test(PipelineCockpit::class, ['task' => $this->task])
            ->call('rejectGate', $gate->id)
            ->assertDispatched('gate-reviewed');

        $this->assertEquals(GateStatus::Rejected, $gate->fresh()->status);
    }

    public function test_approve_gate_not_pending_fails(): void
    {
        $this->actingAs($this->user);

        $run = AgentRun::factory()
            ->for($this->task)
            ->create([
                'agent_type' => 'specs_reviewer',
                'status' => AgentRunStatus::WaitingGate,
            ]);

        $gate = Gate::factory()
            ->for($this->task)
            ->for($run, 'agentRun')
            ->create([
                'gate_type' => GateType::SpecsReview,
                'status' => GateStatus::Approved,
            ]);

        Livewire::test(PipelineCockpit::class, ['task' => $this->task])
            ->call('approveGate', $gate->id)
            ->assertDispatched('error');
    }

    public function test_approve_gate_wrong_task_fails(): void
    {
        $this->actingAs($this->user);

        $otherTask = Task::factory()->for($this->project)->create();
        $run = AgentRun::factory()
            ->for($otherTask)
            ->create([
                'agent_type' => 'specs_reviewer',
                'status' => AgentRunStatus::WaitingGate,
            ]);

        $gate = Gate::factory()
            ->for($otherTask)
            ->for($run, 'agentRun')
            ->create([
                'gate_type' => GateType::SpecsReview,
                'status' => GateStatus::Pending,
            ]);

        Livewire::test(PipelineCockpit::class, ['task' => $this->task])
            ->call('approveGate', $gate->id)
            ->assertNotFound();
    }

    public function test_open_agent_output_dispatches_event(): void
    {
        $this->actingAs($this->user);

        $run = AgentRun::factory()
            ->for($this->task)
            ->create([
                'agent_type' => 'specs_reviewer',
                'status' => AgentRunStatus::Completed,
                'output' => 'test output',
            ]);

        Livewire::test(PipelineCockpit::class, ['task' => $this->task])
            ->call('openAgentOutput', $run->id)
            ->assertDispatched('open-agent-output', runId: $run->id);
    }
}
