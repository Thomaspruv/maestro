<?php

namespace Tests\Feature\Mcp;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Services\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McpTestHelpers;
use Tests\TestCase;

class McpHermesOnlyTest extends TestCase
{
    use McpTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['maestro.internal_pipeline_enabled' => false]);
        $this->setUpMcpAuth();
    }

    public function test_full_hermes_only_workflow(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->mcpUser->id,
            'github_repo' => 'acme/app',
            'github_branch' => 'main',
        ]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Ajouter OAuth',
            'description' => 'Implémenter le flux OAuth GitHub',
            'status' => TaskStatus::Backlog,
        ]);

        app(OrchestratorService::class)->handoffToHermes($task);

        $listResponse = $this->mcp('tools/call', [
            'name' => 'list_hermes_tasks',
            'arguments' => [],
        ]);
        $listResponse->assertOk();
        $list = $this->mcpToolResult($listResponse);

        $this->assertSame('hermes_only', $list['workflow_mode']);
        $this->assertSame(1, $list['count']);
        $this->assertSame($task->id, $list['tasks'][0]['task_id']);
        $this->assertArrayNotHasKey('planning_roles_completed', $list['tasks'][0]);

        $claimResponse = $this->mcp('tools/call', [
            'name' => 'claim_hermes_task',
            'arguments' => ['task_id' => $task->id],
        ]);
        $claimResponse->assertOk();

        $getResponse = $this->mcp('tools/call', [
            'name' => 'get_task',
            'arguments' => ['task_id' => $task->id],
        ]);
        $getResponse->assertOk();
        $detail = $this->mcpToolResult($getResponse);

        $this->assertSame('hermes_only', $detail['hermes']['workflow_mode']);
        $this->assertArrayHasKey('titre', $detail['hermes']['specs_preview']);
        $this->assertArrayHasKey('description', $detail['hermes']['specs_preview']);
        $this->assertArrayNotHasKey('planning_roles_completed', $detail['hermes']);

        $recordResponse = $this->mcp('tools/call', [
            'name' => 'record_step_output',
            'arguments' => [
                'task_id' => $task->id,
                'role' => 'dev',
                'output' => 'OAuth implémenté',
                'model' => 'test-model',
                'cost' => 0,
            ],
        ]);
        $recordResponse->assertOk();
        $record = $this->mcpToolResult($recordResponse);

        $this->assertSame('hermes_only', $record['workflow_mode']);
        $this->assertSame(TaskStatus::Done->value, $record['task']['status']);
    }
}
