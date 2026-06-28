<?php

namespace Tests\Feature;

use App\Enums\PipelineStepStatus;
use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Enums\TaskStatus;
use App\Jobs\ParallelPipelineStepGroupJob;
use App\Models\PipelineStep;
use App\Models\Gate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_can_be_approved(): void
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

        $response = $this->actingAs($user)->post(route('gates.approve', $gate));

        $response->assertRedirect();
        $this->assertSame(GateStatus::Approved, $gate->fresh()->status);
        Queue::assertPushed(ParallelPipelineStepGroupJob::class);
    }

    public function test_gate_reject_requires_feedback(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $run = PipelineStep::factory()->create(['task_id' => $task->id]);
        $gate = Gate::factory()->create([
            'task_id' => $task->id,
            'pipeline_step_id' => $run->id,
            'status' => GateStatus::Pending,
        ]);

        $response = $this->actingAs($user)->post(route('gates.reject', $gate), []);

        $response->assertSessionHasErrors('feedback');
    }
}
