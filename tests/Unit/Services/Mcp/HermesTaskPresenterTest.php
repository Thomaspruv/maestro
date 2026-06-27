<?php

namespace Tests\Unit\Services\Mcp;

use App\Enums\AgentRunStatus;
use App\Enums\TaskStatus;
use App\Models\AgentRun;
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
        AgentRun::factory()->create([
            'task_id' => $task->id,
            'agent_type' => 'dev',
            'status' => AgentRunStatus::Completed,
        ]);

        $this->assertFalse(app(HermesTaskPresenter::class)->isAwaitingHermes($task->fresh('agentRuns')));
    }
}
