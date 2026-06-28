<?php

namespace Tests\Feature\Mcp;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McpTestHelpers;
use Tests\TestCase;

class McpToolsTest extends TestCase
{
    use McpTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcpAuth();
    }

    public function test_list_projects_returns_active_projects(): void
    {
        $active = Project::factory()->create(['user_id' => $this->mcpUser->id, 'name' => 'Active']);
        Project::factory()->create([
            'user_id' => $this->mcpUser->id,
            'status' => ProjectStatus::Archived,
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'list_projects',
            'arguments' => [],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertCount(1, $payload['projects']);
        $this->assertSame($active->id, $payload['projects'][0]['id']);
    }

    public function test_list_tasks_returns_project_tasks(): void
    {
        $project = Project::factory()->create(['user_id' => $this->mcpUser->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
        ]);
        Task::factory()->create(['status' => TaskStatus::Backlog]);

        $response = $this->mcp('tools/call', [
            'name' => 'list_tasks',
            'arguments' => ['project_id' => $project->id],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertCount(1, $payload['tasks']);
        $this->assertSame($task->id, $payload['tasks'][0]['id']);
    }

    public function test_list_tasks_filters_by_status(): void
    {
        $project = Project::factory()->create(['user_id' => $this->mcpUser->id]);
        Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Backlog]);
        $waiting = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::WaitingHermes]);

        $response = $this->mcp('tools/call', [
            'name' => 'list_tasks',
            'arguments' => [
                'project_id' => $project->id,
                'status' => 'waiting_hermes',
            ],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertCount(1, $payload['tasks']);
        $this->assertSame($waiting->id, $payload['tasks'][0]['id']);
    }

    public function test_update_task_status_persists_status(): void
    {
        $project = Project::factory()->create(['user_id' => $this->mcpUser->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Backlog,
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'update_task_status',
            'arguments' => [
                'task_id' => $task->id,
                'status' => 'waiting_hermes',
            ],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertSame('waiting_hermes', $payload['task']['status']);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingHermes->value,
        ]);
    }

    public function test_log_cost_creates_cost_log(): void
    {
        $project = Project::factory()->create(['user_id' => $this->mcpUser->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $response = $this->mcp('tools/call', [
            'name' => 'log_cost',
            'arguments' => [
                'project_id' => $project->id,
                'task_id' => $task->id,
                'model' => 'gpt-4o',
                'input_tokens' => 100,
                'output_tokens' => 50,
                'cost' => 0.42,
            ],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertSame(0.42, $payload['cost_log']['cost']);
        $this->assertDatabaseHas('cost_logs', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'cost' => 0.42,
        ]);
        $this->assertSame(0.42, (float) $task->fresh()->actual_cost);
    }

    public function test_add_agent_output_alias_records_dev_step(): void
    {
        $project = Project::factory()->create(['user_id' => $this->mcpUser->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
            'current_role' => 'hermes',
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'add_agent_output',
            'arguments' => [
                'task_id' => $task->id,
                'agent_type' => 'dev',
                'output' => 'Implémenté via alias legacy',
                'model' => 'test-model',
                'cost' => 0,
            ],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertSame('dev', $payload['pipeline_step']['role']);
        $this->assertSame(TaskStatus::Done->value, $payload['task']['status']);
        $this->assertDatabaseHas('pipeline_steps', [
            'task_id' => $task->id,
            'role' => 'dev',
        ]);
    }
}
