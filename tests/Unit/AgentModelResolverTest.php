<?php

namespace Tests\Unit;

use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentCapabilities;
use Database\Seeders\UserAgentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentModelResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function project_agent_model_takes_priority_over_model_config(): void
    {
        $user = User::factory()->create();
        UserAgentSeeder::seedForUser($user);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'model_config' => ['dev' => 'claude-sonnet-4-6'],
        ]);

        ProjectAgent::factory()->create([
            'project_id' => $project->id,
            'agent_type' => 'dev',
            'model' => 'claude-haiku-4-5',
        ]);

        $this->assertSame(
            'claude-haiku-4-5',
            AgentCapabilities::resolveModel('dev', $project->fresh(['agents'])),
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

        ProjectAgent::factory()->create([
            'project_id' => $project->id,
            'agent_type' => 'pm',
            'model' => 'claude-haiku-4-5',
        ]);

        $run = AgentRun::factory()->create([
            'task_id' => Task::factory()->create(['project_id' => $project->id])->id,
            'agent_type' => 'pm',
            'status' => AgentRunStatus::Running,
            'model' => 'claude-sonnet-4-6',
        ]);

        $this->assertSame(
            'claude-sonnet-4-6',
            AgentCapabilities::resolveModel('pm', $project->fresh(['agents']), $run),
        );
    }

    #[Test]
    public function user_agent_model_is_used_when_project_has_no_override(): void
    {
        $user = User::factory()->create();
        $user->agents()->where('slug', 'qa')->update(['model' => 'claude-haiku-4-5']);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'model_config' => [],
        ]);

        $this->assertSame(
            'claude-haiku-4-5',
            AgentCapabilities::resolveModel('qa', $project->fresh(['agents'])),
        );
    }
}
