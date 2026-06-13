<?php

namespace Tests\Feature;

use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Enums\TaskStatus;
use App\Models\AgentRun;
use App\Models\Gate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_can_be_approved(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress]);
        $run = AgentRun::factory()->create([
            'task_id' => $task->id,
            'agent_type' => 'pm',
            'status' => \App\Enums\AgentRunStatus::Completed,
        ]);
        $gate = Gate::factory()->create([
            'task_id' => $task->id,
            'agent_run_id' => $run->id,
            'gate_type' => GateType::SpecsReview,
            'status' => GateStatus::Pending,
        ]);

        $response = $this->actingAs($user)->post(route('gates.approve', $gate));

        $response->assertRedirect();
        $this->assertSame(GateStatus::Approved, $gate->fresh()->status);
    }

    public function test_gate_reject_requires_feedback(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $run = AgentRun::factory()->create(['task_id' => $task->id]);
        $gate = Gate::factory()->create([
            'task_id' => $task->id,
            'agent_run_id' => $run->id,
            'status' => GateStatus::Pending,
        ]);

        $response = $this->actingAs($user)->post(route('gates.reject', $gate), []);

        $response->assertSessionHasErrors('feedback');
    }
}
