<?php

namespace Tests\Feature;

use App\Enums\PipelineStepStatus;
use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Enums\TaskStatus;
use App\Jobs\ParallelPipelineStepGroupJob;
use App\Livewire\StepOutputViewer;
use App\Livewire\TaskPipeline;
use App\Models\PipelineStep;
use App\Models\Gate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class StepOutputViewerGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['maestro.internal_pipeline_enabled' => true]);
    }

    public function test_approve_gate_from_output_viewer(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress]);
        $run = PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Completed,
        ]);
        $gate = Gate::factory()->create([
            'task_id' => $task->id,
            'pipeline_step_id' => $run->id,
            'gate_type' => GateType::SpecsReview,
            'status' => GateStatus::Pending,
        ]);

        Livewire::actingAs($user)
            ->test(StepOutputViewer::class, ['task' => $task, 'selectedRunId' => $run->id])
            ->assertSee('Approuver')
            ->call('approveGate', $gate->id)
            ->assertHasNoErrors()
            ->assertSet('gateNotice', 'Gate validée — l\'agent suivant démarre.');

        $this->assertSame(GateStatus::Approved, $gate->fresh()->status);
        $this->assertDatabaseHas('pipeline_steps', [
            'task_id' => $task->id,
            'role' => 'ux',
            'status' => PipelineStepStatus::Pending->value,
        ]);
        Queue::assertPushed(ParallelPipelineStepGroupJob::class);
    }

    public function test_approve_gate_from_task_pipeline(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress]);
        $run = PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Completed,
        ]);
        $gate = Gate::factory()->create([
            'task_id' => $task->id,
            'pipeline_step_id' => $run->id,
            'gate_type' => GateType::SpecsReview,
            'status' => GateStatus::Pending,
        ]);

        Livewire::actingAs($user)
            ->test(TaskPipeline::class, ['task' => $task])
            ->assertSee('Approuver')
            ->call('approveGate', $gate->id)
            ->assertHasNoErrors();

        $this->assertSame(GateStatus::Approved, $gate->fresh()->status);
        Queue::assertPushed(ParallelPipelineStepGroupJob::class);
    }
}
