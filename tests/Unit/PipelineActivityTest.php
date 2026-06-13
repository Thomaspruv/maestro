<?php

namespace Tests\Unit;

use App\Enums\AgentRunStatus;
use App\Enums\TaskStatus;
use App\Models\AgentRun;
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
        $task->setRelation('agentRuns', collect([
            new AgentRun(['status' => AgentRunStatus::Running, 'agent_type' => 'pm']),
        ]));

        $this->assertTrue(PipelineActivity::shouldPoll($task));
    }

    #[Test]
    public function agent_message_describes_pm_work(): void
    {
        $this->assertStringContainsString('specs', PipelineActivity::agentMessage('pm'));
    }

    #[Test]
    public function current_agent_type_reads_string_column(): void
    {
        $task = new Task(['current_agent' => 'ux']);

        $this->assertSame('ux', PipelineActivity::currentAgentType($task));
    }
}
