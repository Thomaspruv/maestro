<?php

namespace Tests\Unit;

use App\Enums\PipelineStepStatus;
use App\Enums\TaskStatus;
use App\Models\PipelineStep;
use App\Models\Task;
use App\Support\PipelineActivity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PipelineActivityTest extends TestCase
{
    #[Test]
    public function should_poll_when_task_is_in_progress(): void
    {
        $task = new Task(['status' => TaskStatus::InProgress]);

        $this->assertTrue(PipelineActivity::shouldPoll($task));
    }

    #[Test]
    public function should_poll_when_an_agent_is_running(): void
    {
        $task = new Task(['status' => TaskStatus::Done]);
        $task->setRelation('pipelineSteps', collect([
            new PipelineStep(['status' => PipelineStepStatus::Running, 'role' => 'pm']),
        ]));

        $this->assertTrue(PipelineActivity::shouldPoll($task));
    }

    #[Test]
    public function agent_message_describes_pm_work(): void
    {
        $this->assertStringContainsString('specs', PipelineActivity::roleMessage('pm'));
    }

    #[Test]
    public function current_role_type_reads_string_column(): void
    {
        $task = new Task(['current_role' => 'ux']);

        $this->assertSame('ux', PipelineActivity::currentPipelineRoleSlug($task));
    }
}
