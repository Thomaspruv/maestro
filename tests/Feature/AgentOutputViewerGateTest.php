<?php

namespace Tests\Feature;

use App\Enums\AgentRunStatus;
use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Enums\TaskStatus;
use App\Jobs\ParallelAgentGroupJob;
use App\Livewire\AgentOutputViewer;
use App\Livewire\TaskPipeline;
use App\Models\AgentRun;
use App\Models\Gate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AgentOutputViewerGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_gate_from_output_viewer(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress]);
        $run = AgentRun::factory()->create([
            'task_id' => $task->id,
            'agent_type' => 'pm',
            'status' => AgentRunStatus::Completed,
        ]);
        $gate = Gate::factory()->create([
            'task_id' => $task->id,
            'agent_run_id' => $run->id,
            'gate_type' => GateType::SpecsReview,
            'status' => GateStatus::Pending,
        ]);

        Livewire::actingAs($user)
            ->test(AgentOutputViewer::class, ['task' => $task, 'selectedRunId' => $run->id])
            ->assertSee('Approuver')
            ->call('approveGate', $gate->id)
            ->assertHasNoErrors()
            ->assertSet('gateNotice', 'Gate validée — l\'agent suivant démarre.');

        $this->assertSame(GateStatus::Approved, $gate->fresh()->status);
        $this->assertDatabaseHas('agent_runs', [
            'task_id' => $task->id,
            'agent_type' => 'ux',
            'status' => AgentRunStatus::Pending->value,
        ]);
        Queue::assertPushed(ParallelAgentGroupJob::class);
    }

    public function test_approve_gate_from_task_pipeline(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress]);
        $run = AgentRun::factory()->create([
            'task_id' => $task->id,
            'agent_type' => 'pm',
            'status' => AgentRunStatus::Completed,
        ]);
        $gate = Gate::factory()->create([
            'task_id' => $task->id,
            'agent_run_id' => $run->id,
            'gate_type' => GateType::SpecsReview,
            'status' => GateStatus::Pending,
        ]);

        Livewire::actingAs($user)
            ->test(TaskPipeline::class, ['task' => $task])
            ->assertSee('Approuver')
            ->call('approveGate', $gate->id)
            ->assertHasNoErrors();

        $this->assertSame(GateStatus::Approved, $gate->fresh()->status);
        Queue::assertPushed(ParallelAgentGroupJob::class);
    }
}
