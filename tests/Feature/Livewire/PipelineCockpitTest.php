<?php

namespace Tests\Feature\Livewire;

use App\Enums\PipelineStepStatus;
use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Enums\TaskStatus;
use App\Livewire\PipelineCockpit;
use App\Models\PipelineStep;
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

    public function test_cockpit_renders_for_authorized_user(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);

        Livewire::actingAs($user)
            ->test(PipelineCockpit::class, ['task' => $task])
            ->assertSuccessful();
    }

    public function test_cockpit_denies_unauthorized_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $other->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);

        Livewire::actingAs($user)
            ->test(PipelineCockpit::class, ['task' => $task])
            ->assertForbidden();
    }

    public function test_cockpit_displays_snapshot(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $task->pipelineSteps()->create([
            'role' => 'pm',
            'status' => PipelineStepStatus::Completed,
            'cost' => 0.1234,
            'input' => [],
            'output' => 'Some output',
            'model' => 'claude-opus-4-8',
        ]);

        Livewire::actingAs($user)
            ->test(PipelineCockpit::class, ['task' => $task])
            ->assertSuccessful()
            ->assertSet('snapshot.total_cost', 0.1234)
            ->assertSet('snapshot.is_active', false);
    }

    public function test_cockpit_approves_gate(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
        ]);
        $run = PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::WaitingGate,
            'output' => 'Output',
        ]);
        $gate = Gate::create([
            'task_id' => $task->id,
            'pipeline_step_id' => $run->id,
            'gate_type' => GateType::SpecsReview,
            'status' => GateStatus::Pending,
        ]);

        Livewire::actingAs($user)
            ->test(PipelineCockpit::class, ['task' => $task])
            ->call('approveGate', $gate->id);

        $this->assertSame(GateStatus::Approved, $gate->fresh()->status);
    }

    public function test_cockpit_rejects_gate(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
        ]);
        $run = PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::WaitingGate,
            'output' => 'Output',
        ]);
        $gate = Gate::create([
            'task_id' => $task->id,
            'pipeline_step_id' => $run->id,
            'gate_type' => GateType::SpecsReview,
            'status' => GateStatus::Pending,
        ]);

        Livewire::actingAs($user)
            ->test(PipelineCockpit::class, ['task' => $task])
            ->call('rejectGate', $gate->id, 'feedback text');

        $gate->refresh();
        $this->assertSame(GateStatus::Pending, $gate->status);
        $this->assertStringContainsString('feedback text', $gate->feedback ?? '');
    }

    public function test_cockpit_cannot_approve_non_pending_gate(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
        ]);
        $run = PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Completed,
        ]);
        $gate = Gate::create([
            'task_id' => $task->id,
            'pipeline_step_id' => $run->id,
            'gate_type' => GateType::SpecsReview,
            'status' => GateStatus::Approved,
        ]);

        Livewire::actingAs($user)
            ->test(PipelineCockpit::class, ['task' => $task])
            ->call('approveGate', $gate->id)
            ->assertDispatched('error');
    }
}
