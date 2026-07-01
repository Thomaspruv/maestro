<?php

namespace Tests\Unit\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Services\KanbanColumnResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class KanbanColumnResolverTest extends TestCase
{
    use RefreshDatabase;

    private KanbanColumnResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(KanbanColumnResolver::class);
    }

    public function test_resolve_column_maps_lifecycle_and_roles(): void
    {
        $project = Project::factory()->create();

        $backlog = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Backlog]);
        $failed = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Failed]);
        $done = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Done]);
        $dev = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::WaitingHermes, 'current_role' => 'hermes']);
        $pm = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress, 'current_role' => 'pm']);
        $testLead = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress, 'current_role' => 'test_lead']);
        $qaUx = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress, 'current_role' => 'qa_ux']);
        $legacyTechLead = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress, 'current_role' => 'tech_lead']);
        $legacyReview = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InReview]);

        $this->assertSame('backlog', $this->resolver->resolveColumn($backlog));
        $this->assertSame('backlog', $this->resolver->resolveColumn($failed));
        $this->assertSame('done', $this->resolver->resolveColumn($done));
        $this->assertSame('dev', $this->resolver->resolveColumn($dev));
        $this->assertSame('pm', $this->resolver->resolveColumn($pm));
        $this->assertSame('test_lead', $this->resolver->resolveColumn($testLead));
        $this->assertSame('qa_ux', $this->resolver->resolveColumn($qaUx));
        $this->assertSame('test_lead', $this->resolver->resolveColumn($legacyTechLead));
        $this->assertSame('qa', $this->resolver->resolveColumn($legacyReview));
    }

    public function test_apply_column_updates_task_state(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Backlog,
            'current_role' => null,
        ]);

        $this->resolver->applyColumn($task, 'pm');
        $task->refresh();
        $this->assertSame(TaskStatus::InProgress, $task->status);
        $this->assertSame('pm', $task->current_role);

        $this->resolver->applyColumn($task, 'dev');
        $task->refresh();
        $this->assertSame(TaskStatus::WaitingHermes, $task->status);
        $this->assertSame('hermes', $task->current_role);

        $this->resolver->applyColumn($task, 'done');
        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertNull($task->current_role);

        $this->resolver->applyColumn($task, 'backlog');
        $task->refresh();
        $this->assertSame(TaskStatus::Backlog, $task->status);
        $this->assertNull($task->current_role);
    }

    public function test_apply_column_rejects_unknown_column(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->resolver->applyColumn($task, 'unknown');
    }

    public function test_group_tasks_by_column_returns_all_columns(): void
    {
        $project = Project::factory()->create();
        Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Backlog]);
        Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress, 'current_role' => 'qa']);

        $grouped = $this->resolver->groupTasksByColumn($project->tasks()->get());

        foreach ($this->resolver->columnOrder() as $slug) {
            $this->assertArrayHasKey($slug, $grouped);
        }

        $this->assertCount(1, $grouped['backlog']);
        $this->assertCount(1, $grouped['qa']);
    }
}
