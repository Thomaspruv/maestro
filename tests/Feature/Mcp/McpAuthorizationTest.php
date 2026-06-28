<?php

namespace Tests\Feature\Mcp;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\McpTestHelpers;
use Tests\TestCase;

class McpAuthorizationTest extends TestCase
{
    use McpTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcpAuth();
    }

    public function test_cannot_get_other_users_task(): void
    {
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $otherUser->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $response = $this->mcp('tools/call', [
            'name' => 'get_task',
            'arguments' => ['task_id' => $task->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Ressource introuvable : task');
    }

    public function test_cannot_create_task_in_other_users_project(): void
    {
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->mcp('tools/call', [
            'name' => 'create_task',
            'arguments' => [
                'project_id' => $project->id,
                'title' => 'Intrusion',
                'type' => 'feature',
                'priority' => 'high',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Ressource introuvable : project');
    }

    public function test_cannot_list_tasks_from_other_users_project(): void
    {
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->mcp('tools/call', [
            'name' => 'list_tasks',
            'arguments' => ['project_id' => $project->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Ressource introuvable : project');
    }
}
