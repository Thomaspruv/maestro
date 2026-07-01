<?php

namespace Tests\Feature\Mcp;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McpTestHelpers;
use Tests\TestCase;

class McpKanbanTest extends TestCase
{
    use McpTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcpAuth();
    }

    public function test_list_kanban_board_returns_columns_in_order(): void
    {
        $project = Project::factory()->create(['user_id' => $this->mcpUser->id]);
        Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Backlog, 'title' => 'Backlog task']);
        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
            'current_role' => 'pm',
            'title' => 'PM task',
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
            'current_role' => 'hermes',
            'title' => 'Dev task',
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'list_kanban_board',
            'arguments' => ['project_id' => $project->id],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertSame(config('maestro.kanban_column_order'), $payload['column_order']);
        $this->assertCount(9, $payload['columns']);

        $backlog = collect($payload['columns'])->firstWhere('slug', 'backlog');
        $pm = collect($payload['columns'])->firstWhere('slug', 'pm');
        $dev = collect($payload['columns'])->firstWhere('slug', 'dev');

        $this->assertCount(1, $backlog['tasks']);
        $this->assertSame('Backlog task', $backlog['tasks'][0]['title']);
        $this->assertCount(1, $pm['tasks']);
        $this->assertSame('pm', $pm['tasks'][0]['current_role']);
        $this->assertCount(1, $dev['tasks']);
        $this->assertSame('dev', $dev['tasks'][0]['kanban_column']);
    }

    public function test_move_task_applies_kanban_column(): void
    {
        $project = Project::factory()->create(['user_id' => $this->mcpUser->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
            'current_role' => 'pm',
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'move_task',
            'arguments' => [
                'task_id' => $task->id,
                'kanban_column' => 'qa',
            ],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertSame('qa', $payload['task']['kanban_column']);
        $this->assertSame('qa', $payload['task']['current_role']);
        $this->assertSame(TaskStatus::InProgress->value, $payload['task']['status']);
    }

    public function test_list_tasks_filters_by_kanban_column(): void
    {
        $project = Project::factory()->create(['user_id' => $this->mcpUser->id]);
        Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Backlog]);
        $devTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
            'current_role' => 'hermes',
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'list_tasks',
            'arguments' => [
                'project_id' => $project->id,
                'kanban_column' => 'dev',
            ],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertCount(1, $payload['tasks']);
        $this->assertSame($devTask->id, $payload['tasks'][0]['id']);
        $this->assertSame('dev', $payload['tasks'][0]['kanban_column']);
    }

    public function test_get_task_includes_kanban_column(): void
    {
        $project = Project::factory()->create(['user_id' => $this->mcpUser->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
            'current_role' => 'security',
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'get_task',
            'arguments' => ['task_id' => $task->id],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertSame('security', $payload['task']['kanban_column']);
    }
}
