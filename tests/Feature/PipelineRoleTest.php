<?php

namespace Tests\Feature;

use App\Models\PipelineRole;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectRoleSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_gets_builtin_roles_on_factory_create(): void
    {
        $user = User::factory()->create();

        $this->assertCount(9, $user->pipelineRoles);
        $this->assertTrue($user->pipelineRoles()->where('slug', 'pm')->first()->is_builtin);
    }

    public function test_cannot_delete_builtin_role(): void
    {
        $user = User::factory()->create();
        $builtin = $user->pipelineRoles()->where('slug', 'pm')->first();

        $this->assertFalse($user->can('delete', $builtin));
    }

    public function test_can_delete_custom_role(): void
    {
        $user = User::factory()->create();

        $custom = PipelineRole::factory()->create([
            'user_id' => $user->id,
            'slug' => 'custom_role',
        ]);

        $this->assertTrue($user->can('delete', $custom));

        $custom->delete();

        $this->assertDatabaseMissing('pipeline_roles', ['id' => $custom->id]);
    }

    public function test_project_creation_copies_pipeline_roles_including_custom(): void
    {
        $user = User::factory()->create();

        PipelineRole::factory()->create([
            'user_id' => $user->id,
            'slug' => 'legal_reviewer',
            'name' => 'Legal Reviewer',
        ]);

        $project = Project::factory()->create(['user_id' => $user->id]);
        app(ProjectRoleSyncService::class)->copyUserRolesToProject($user, $project);

        $this->assertCount(10, $project->roles);
        $this->assertTrue($project->roles()->where('role', 'legal_reviewer')->exists());
    }
}
