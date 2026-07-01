<?php

namespace Tests\Feature\Mcp;

use App\Enums\PipelineStepStatus;
use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Enums\TaskStatus;
use App\Events\GatePending;
use App\Jobs\RunPipelineStepJob;
use App\Models\PipelineStep;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Support\McpTestHelpers;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use McpTestHelpers;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->setUpMcpAuth($this->user);
    }

    public function test_initialize_returns_capabilities(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.$this->mcpPlainToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'maestro')
            ->assertJsonStructure(['result' => ['capabilities' => ['tools']]]);
    }

    public function test_tools_list_returns_all_tools(): void
    {
        $response = $this->mcp('tools/list');

        $response->assertOk();
        $tools = $response->json('result.tools');
        $names = collect($tools)->pluck('name')->all();

        $this->assertContains('list_projects', $names);
        $this->assertContains('create_task', $names);
        $this->assertContains('record_step_output', $names);
        $this->assertContains('request_gate', $names);
        $this->assertContains('list_hermes_tasks', $names);
        $this->assertContains('claim_hermes_task', $names);
        $this->assertContains('add_agent_output', $names);
        $this->assertContains('list_kanban_board', $names);
        $this->assertContains('move_task', $names);
        $this->assertCount(13, $names);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertUnauthorized();
    }

    public function test_create_task_persists_task(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $response = $this->mcp('tools/call', [
            'name' => 'create_task',
            'arguments' => [
                'project_id' => $project->id,
                'title' => 'Via Hermes',
                'description' => 'Description test',
                'type' => 'feature',
                'priority' => 'high',
            ],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertDatabaseHas('tasks', [
            'id' => $payload['task']['id'],
            'title' => 'Via Hermes',
            'project_id' => $project->id,
        ]);
    }

    public function test_record_step_output_creates_run_and_cost_log(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $response = $this->mcp('tools/call', [
            'name' => 'record_step_output',
            'arguments' => [
                'task_id' => $task->id,
                'role' => 'qa',
                'output' => 'Tests OK',
                'model' => 'deepseek/deepseek-chat',
                'input_tokens' => 100,
                'output_tokens' => 50,
                'cost' => 0.01,
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('pipeline_steps', [
            'task_id' => $task->id,
            'role' => 'qa',
            'status' => PipelineStepStatus::Completed->value,
        ]);

        $this->assertDatabaseHas('cost_logs', [
            'task_id' => $task->id,
            'cost' => 0.01,
        ]);
    }

    public function test_request_gate_creates_gate_and_broadcasts_event(): void
    {
        Event::fake([GatePending::class]);

        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $run = PipelineStep::factory()->create(['task_id' => $task->id]);

        $response = $this->mcp('tools/call', [
            'name' => 'request_gate',
            'arguments' => [
                'task_id' => $task->id,
                'pipeline_step_id' => $run->id,
                'gate_type' => 'gate_tech',
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('gates', [
            'task_id' => $task->id,
            'pipeline_step_id' => $run->id,
            'gate_type' => GateType::TechReview->value,
            'status' => GateStatus::Pending->value,
        ]);

        Event::assertDispatched(GatePending::class);
    }

    public function test_record_step_output_dev_resumes_orchestrator_from_waiting_hermes(): void
    {
        Bus::fake();

        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
            'current_role' => 'hermes',
        ]);

        foreach (['pm', 'test_lead', 'ux', 'security'] as $agent) {
            PipelineStep::factory()->create([
                'task_id' => $task->id,
                'role' => $agent,
                'status' => PipelineStepStatus::Completed,
            ]);
        }

        $response = $this->mcp('tools/call', [
            'name' => 'record_step_output',
            'arguments' => [
                'task_id' => $task->id,
                'role' => 'dev',
                'output' => 'Code implémenté par Hermes',
                'model' => 'deepseek/deepseek-chat',
                'cost' => 0,
            ],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertDatabaseHas('pipeline_steps', [
            'task_id' => $task->id,
            'role' => 'dev',
            'status' => PipelineStepStatus::Completed->value,
        ]);

        $this->assertSame('hermes_only', $payload['workflow_mode']);
        $this->assertSame(TaskStatus::Done->value, $payload['task']['status']);

        Bus::assertNotDispatched(RunPipelineStepJob::class);
    }

    public function test_record_step_output_dev_resumes_internal_pipeline_when_enabled(): void
    {
        Bus::fake();
        config(['maestro.internal_pipeline_enabled' => true]);

        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
            'current_role' => 'hermes',
        ]);

        foreach (['pm', 'test_lead', 'ux', 'security'] as $agent) {
            PipelineStep::factory()->create([
                'task_id' => $task->id,
                'role' => $agent,
                'status' => PipelineStepStatus::Completed,
            ]);
        }

        $response = $this->mcp('tools/call', [
            'name' => 'record_step_output',
            'arguments' => [
                'task_id' => $task->id,
                'role' => 'dev',
                'output' => 'Code implémenté par Hermes',
                'model' => 'deepseek/deepseek-chat',
                'cost' => 0,
            ],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertSame('internal_pipeline', $payload['workflow_mode']);
        Bus::assertDispatched(RunPipelineStepJob::class, fn (RunPipelineStepJob $job) => $job->role === 'qa');
    }

    public function test_list_hermes_tasks_returns_tasks_ready_for_hermes(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $ready = Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Prête pour Hermes',
            'status' => TaskStatus::WaitingHermes,
            'current_role' => 'hermes',
            'priority' => 'high',
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Encore en planning',
            'status' => TaskStatus::InProgress,
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'list_hermes_tasks',
            'arguments' => [],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertSame('hermes_only', $payload['workflow_mode']);
        $this->assertSame(1, $payload['count']);
        $this->assertSame($ready->id, $payload['tasks'][0]['task_id']);
        $this->assertSame('implement_dev', $payload['tasks'][0]['hermes_action']);
        $this->assertStringContainsString('claim_hermes_task', $payload['polling_hint']);
    }

    public function test_list_hermes_tasks_excludes_tasks_with_completed_dev_run(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
        ]);
        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'dev',
            'status' => PipelineStepStatus::Completed,
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'list_hermes_tasks',
            'arguments' => [],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertSame(0, $payload['count']);
    }

    public function test_claim_hermes_task_reserves_task_for_processing(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
            'current_role' => 'hermes',
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'claim_hermes_task',
            'arguments' => ['task_id' => $task->id],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertTrue($payload['claimed']);
        $this->assertTrue($payload['hermes']['should_process']);
        $this->assertSame('hermes_only', $payload['hermes']['workflow_mode']);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::InProgress->value,
            'current_role' => 'hermes',
        ]);
    }

    public function test_claim_hermes_task_rejects_already_claimed_task(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
            'current_role' => 'hermes',
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'claim_hermes_task',
            'arguments' => ['task_id' => $task->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Cette tâche n\'est pas disponible pour Hermes (statut ou run dev déjà présent).');
    }

    public function test_get_task_includes_hermes_detail_block_in_hermes_only_mode(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'github_repo' => 'acme/maestro',
            'github_branch' => 'main',
        ]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'OAuth GitHub',
            'description' => 'Implémenter le flux OAuth',
            'status' => TaskStatus::WaitingHermes,
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'get_task',
            'arguments' => ['task_id' => $task->id],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertTrue($payload['hermes']['should_process']);
        $this->assertSame('implement_dev', $payload['hermes']['action']);
        $this->assertSame('hermes_only', $payload['hermes']['workflow_mode']);
        $this->assertSame('acme/maestro', $payload['hermes']['github']['repo']);
        $this->assertArrayHasKey('titre', $payload['hermes']['specs_preview']);
        $this->assertArrayNotHasKey('planning_roles_completed', $payload['hermes']);
    }

    public function test_get_task_includes_planning_specs_when_internal_pipeline_enabled(): void
    {
        config(['maestro.internal_pipeline_enabled' => true]);

        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'github_repo' => 'acme/maestro',
            'github_branch' => 'main',
        ]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
        ]);
        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'tech_lead',
            'status' => PipelineStepStatus::Completed,
            'output' => 'Specs techniques détaillées',
        ]);

        $response = $this->mcp('tools/call', [
            'name' => 'get_task',
            'arguments' => ['task_id' => $task->id],
        ]);

        $response->assertOk();
        $payload = $this->mcpToolResult($response);

        $this->assertSame('internal_pipeline', $payload['hermes']['workflow_mode']);
        $this->assertArrayHasKey('tech_lead', $payload['hermes']['specs_preview']);
        $this->assertArrayHasKey('planning_roles_completed', $payload['hermes']);
    }
}
