<?php

namespace Tests\Unit;

use App\Enums\PipelineStepStatus;
use App\Models\PipelineStep;
use App\Models\Project;
use App\Models\ProjectRole;
use App\Models\Task;
use App\Models\User;
use App\Services\PipelineRoleCapabilities;
use Database\Seeders\PipelineRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PipelineRoleModelResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function project_agent_model_takes_priority_over_model_config(): void
    {
        $user = User::factory()->create();
        PipelineRoleSeeder::seedForUser($user);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'model_config' => ['dev' => 'claude-sonnet-4-6'],
        ]);

        ProjectRole::factory()->create([
            'project_id' => $project->id,
            'role' => 'dev',
            'model' => 'claude-haiku-4-5',
        ]);

        $this->assertSame(
            'claude-haiku-4-5',
            PipelineRoleCapabilities::resolveModel('dev', $project->fresh(['roles'])),
        );
    }

    #[Test]
    public function frozen_agent_run_model_is_not_overridden(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'model_config' => ['pm' => 'claude-haiku-4-5'],
        ]);

        ProjectRole::factory()->create([
            'project_id' => $project->id,
            'role' => 'pm',
            'model' => 'claude-haiku-4-5',
        ]);

        $run = PipelineStep::factory()->create([
            'task_id' => Task::factory()->create(['project_id' => $project->id])->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Running,
            'model' => 'claude-sonnet-4-6',
        ]);

        $this->assertSame(
            'claude-sonnet-4-6',
            PipelineRoleCapabilities::resolveModel('pm', $project->fresh(['roles']), $run),
        );
    }

    #[Test]
    public function user_agent_model_is_used_when_project_has_no_override(): void
    {
        $user = User::factory()->create();
        $user->pipelineRoles()->where('slug', 'qa')->update(['model' => 'claude-haiku-4-5']);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'model_config' => [],
        ]);

        $this->assertSame(
            'claude-haiku-4-5',
            PipelineRoleCapabilities::resolveModel('qa', $project->fresh(['roles'])),
        );
    }
}
