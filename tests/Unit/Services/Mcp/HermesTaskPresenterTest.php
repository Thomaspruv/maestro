<?php

namespace Tests\Unit\Services\Mcp;

use App\Enums\PipelineStepStatus;
use App\Enums\TaskStatus;
use App\Models\PipelineStep;
use App\Models\Project;
use App\Models\Task;
use App\Services\Mcp\HermesTaskPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HermesTaskPresenterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_detects_task_awaiting_hermes(): void
    {
        $task = Task::factory()->create([
            'status' => TaskStatus::WaitingHermes,
        ]);

        $this->assertTrue(app(HermesTaskPresenter::class)->isAwaitingHermes($task));
    }

    #[Test]
    public function it_rejects_task_with_completed_dev_run(): void
    {
        $task = Task::factory()->create([
            'status' => TaskStatus::WaitingHermes,
        ]);
        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'dev',
            'status' => PipelineStepStatus::Completed,
        ]);

        $this->assertFalse(app(HermesTaskPresenter::class)->isAwaitingHermes($task->fresh('pipelineSteps')));
    }

    #[Test]
    public function list_item_uses_hermes_only_workflow_mode_by_default(): void
    {
        config(['maestro.internal_pipeline_enabled' => false]);

        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
        ]);

        $item = app(HermesTaskPresenter::class)->listItem($task->fresh(['project', 'pipelineSteps']));

        $this->assertSame('hermes_only', $item['workflow_mode']);
        $this->assertArrayNotHasKey('planning_roles_completed', $item);
    }

    #[Test]
    public function detail_block_includes_planning_roles_when_internal_pipeline_enabled(): void
    {
        config(['maestro.internal_pipeline_enabled' => true]);

        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
        ]);
        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Completed,
        ]);

        $block = app(HermesTaskPresenter::class)->detailBlock($task->fresh(['project', 'pipelineSteps']));

        $this->assertSame('internal_pipeline', $block['workflow_mode']);
        $this->assertContains('pm', $block['planning_roles_completed']);
    }
}
