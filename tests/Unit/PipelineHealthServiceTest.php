<?php

namespace Tests\Unit;

use App\Enums\AgentRunStatus;
use App\Enums\PipelineHealthState;
use App\Enums\TaskStatus;
use App\Models\AgentRun;
use App\Models\Task;
use App\Services\PipelineHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PipelineHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function not_started_for_backlog_task(): void
    {
        $task = new Task(['status' => TaskStatus::Backlog]);
        $task->setRelation('agentRuns', collect());
        $task->setRelation('gates', collect());

        $health = app(PipelineHealthService::class)->forTask($task, ['pm', 'dev']);

        $this->assertSame(PipelineHealthState::NotStarted, $health['state']);
        $this->assertSame(0, $health['progress']);
    }

    #[Test]
    public function queued_when_pending_agent_run_exists(): void
    {
        $task = new Task(['status' => TaskStatus::InProgress]);
        $task->setRelation('agentRuns', collect([
            new AgentRun([
                'agent_type' => 'pm',
                'status' => AgentRunStatus::Pending,
                'created_at' => now(),
            ]),
        ]));
        $task->setRelation('gates', collect());

        config(['queue.default' => 'sync']);

        $health = app(PipelineHealthService::class)->forTask($task, ['pm', 'dev']);

        $this->assertSame(PipelineHealthState::Queued, $health['state']);
        $this->assertStringContainsString('file', strtolower($health['title']));
    }

    #[Test]
    public function running_when_agent_run_is_active(): void
    {
        $task = new Task(['status' => TaskStatus::InProgress, 'current_agent' => 'pm']);
        $task->setRelation('agentRuns', collect([
            new AgentRun([
                'agent_type' => 'pm',
                'status' => AgentRunStatus::Running,
                'started_at' => now(),
            ]),
        ]));
        $task->setRelation('gates', collect());

        $health = app(PipelineHealthService::class)->forTask($task, ['pm', 'dev']);

        $this->assertSame(PipelineHealthState::Running, $health['state']);
    }

    #[Test]
    public function sync_queue_never_reports_blocked_worker(): void
    {
        config(['queue.default' => 'sync']);

        $task = Task::factory()->create(['status' => TaskStatus::InProgress]);
        AgentRun::factory()->create([
            'task_id' => $task->id,
            'agent_type' => 'pm',
            'status' => AgentRunStatus::Pending,
        ]);

        $health = app(PipelineHealthService::class)->forTask($task->fresh(['agentRuns', 'gates']), ['pm']);

        $this->assertNotSame(PipelineHealthState::BlockedWorker, $health['state']);
    }

    #[Test]
    public function database_queue_with_pending_jobs_reports_blocked_worker(): void
    {
        config(['queue.default' => 'database']);

        $task = Task::factory()->create(['status' => TaskStatus::InProgress, 'current_agent' => 'ux']);
        AgentRun::factory()->create([
            'task_id' => $task->id,
            'agent_type' => 'ux',
            'status' => AgentRunStatus::Pending,
            'created_at' => now()->subMinute(),
        ]);

        DB::table('jobs')->insert([
            'queue' => 'agents',
            'payload' => json_encode(['job' => 'test', 'data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $health = app(PipelineHealthService::class)->forTask(
            $task->fresh(['agentRuns', 'gates']),
            ['pm', 'ux', 'dev'],
        );

        $this->assertSame(PipelineHealthState::BlockedWorker, $health['state']);
        $this->assertStringContainsString('database', strtolower($health['message']));
    }
}
