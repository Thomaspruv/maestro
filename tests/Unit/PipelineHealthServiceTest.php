<?php

namespace Tests\Unit;

use App\Enums\PipelineStepStatus;
use App\Enums\PipelineHealthState;
use App\Enums\TaskStatus;
use App\Models\PipelineStep;
use App\Models\Project;
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
        $task->setRelation('pipelineSteps', collect());
        $task->setRelation('gates', collect());

        $health = app(PipelineHealthService::class)->forTask($task, ['pm', 'dev']);

        $this->assertSame(PipelineHealthState::NotStarted, $health['state']);
        $this->assertSame(0, $health['progress']);
    }

    #[Test]
    public function queued_when_pending_agent_run_exists(): void
    {
        $task = new Task(['status' => TaskStatus::InProgress]);
        $task->setRelation('pipelineSteps', collect([
            new PipelineStep([
                'role' => 'pm',
                'status' => PipelineStepStatus::Pending,
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
        $task = new Task(['status' => TaskStatus::InProgress, 'current_role' => 'pm']);
        $task->setRelation('pipelineSteps', collect([
            new PipelineStep([
                'role' => 'pm',
                'status' => PipelineStepStatus::Running,
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
        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Pending,
        ]);

        $health = app(PipelineHealthService::class)->forTask($task->fresh(['pipelineSteps', 'gates']), ['pm']);

        $this->assertNotSame(PipelineHealthState::BlockedWorker, $health['state']);
    }

    #[Test]
    public function database_queue_with_pending_jobs_reports_blocked_worker(): void
    {
        config(['queue.default' => 'database', 'maestro.internal_pipeline_enabled' => true]);

        $task = Task::factory()->create(['status' => TaskStatus::InProgress, 'current_role' => 'ux']);
        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'ux',
            'status' => PipelineStepStatus::Pending,
            'created_at' => now()->subMinute(),
        ]);

        DB::table('jobs')->insert([
            'queue' => 'roles',
            'payload' => json_encode(['job' => 'test', 'data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $health = app(PipelineHealthService::class)->forTask(
            $task->fresh(['pipelineSteps', 'gates']),
            ['pm', 'ux', 'dev'],
        );

        $this->assertSame(PipelineHealthState::BlockedWorker, $health['state']);
        $this->assertStringContainsString('composer dev', strtolower($health['message']));
    }

    #[Test]
    public function it_ignores_superseded_failed_runs_when_pipeline_recovered(): void
    {
        $task = Task::factory()->create([
            'status' => TaskStatus::InProgress,
            'current_role' => 'dev',
        ]);

        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'tech_lead',
            'status' => PipelineStepStatus::Failed,
            'error_message' => 'cURL error 28: Operation timed out after 60004 milliseconds',
        ]);

        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'tech_lead',
            'status' => PipelineStepStatus::Completed,
        ]);

        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'dev',
            'status' => PipelineStepStatus::Failed,
            'error_message' => 'git pull failed',
        ]);

        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'dev',
            'status' => PipelineStepStatus::Running,
            'started_at' => now(),
        ]);

        $health = app(PipelineHealthService::class)->forTask(
            $task->fresh(['pipelineSteps', 'gates']),
            ['pm', 'ux', 'tech_lead', 'dev'],
        );

        $this->assertSame(PipelineHealthState::Running, $health['state']);
        $this->assertSame('dev', $health['current_role']);
    }

    #[Test]
    public function waiting_hermes_reports_hermes_state(): void
    {
        $task = new Task([
            'status' => TaskStatus::WaitingHermes,
            'current_role' => 'hermes',
        ]);
        $task->setRelation('pipelineSteps', collect());
        $task->setRelation('gates', collect());

        $health = app(PipelineHealthService::class)->forTask($task, ['pm', 'ux', 'tech_lead', 'security', 'qa']);

        $this->assertSame(PipelineHealthState::WaitingHermes, $health['state']);
        $this->assertSame('En attente d\'Hermes', $health['title']);
        $this->assertStringContainsString('cron MCP', $health['message']);
    }

    #[Test]
    public function kanban_banner_hidden_when_internal_pipeline_disabled(): void
    {
        config(['queue.default' => 'redis', 'maestro.internal_pipeline_enabled' => false]);

        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
        ]);

        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Pending,
            'created_at' => now()->subMinute(),
        ]);

        $banner = app(PipelineHealthService::class)->kanbanWorkerBanner($project->fresh());

        $this->assertFalse($banner['show']);
    }

    #[Test]
    public function kanban_banner_hidden_when_database_queue_is_idle(): void
    {
        config(['queue.default' => 'database', 'maestro.internal_pipeline_enabled' => true]);

        $project = Project::factory()->create();

        $banner = app(PipelineHealthService::class)->kanbanWorkerBanner($project);

        $this->assertFalse($banner['show']);
    }

    #[Test]
    public function kanban_banner_shows_database_worker_message_not_horizon_when_jobs_pending(): void
    {
        config(['queue.default' => 'database', 'maestro.internal_pipeline_enabled' => true]);

        $project = Project::factory()->create();

        DB::table('jobs')->insert([
            'queue' => 'roles',
            'payload' => json_encode(['job' => 'test', 'data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $banner = app(PipelineHealthService::class)->kanbanWorkerBanner($project);

        $this->assertTrue($banner['show']);
        $this->assertStringContainsString('Worker queue inactif', $banner['title']);
        $this->assertStringNotContainsString('Horizon', $banner['message']);
        $this->assertFalse($banner['show_horizon_link']);
    }

    #[Test]
    public function kanban_banner_hidden_when_worker_is_processing_reserved_job(): void
    {
        config(['queue.default' => 'database', 'maestro.internal_pipeline_enabled' => true]);

        $project = Project::factory()->create();

        DB::table('jobs')->insert([
            'queue' => 'roles',
            'payload' => json_encode(['job' => 'test', 'data' => []]),
            'attempts' => 1,
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        DB::table('jobs')->insert([
            'queue' => 'roles',
            'payload' => json_encode(['job' => 'test2', 'data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $banner = app(PipelineHealthService::class)->kanbanWorkerBanner($project);

        $this->assertFalse($banner['show']);
    }

    #[Test]
    public function task_not_blocked_when_database_worker_is_active(): void
    {
        config(['queue.default' => 'database', 'maestro.internal_pipeline_enabled' => true]);

        $task = Task::factory()->create(['status' => TaskStatus::InProgress]);

        DB::table('jobs')->insert([
            'queue' => 'roles',
            'payload' => json_encode(['job' => 'test', 'data' => []]),
            'attempts' => 1,
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'pm',
            'status' => PipelineStepStatus::Running,
            'started_at' => now(),
        ]);

        $health = app(PipelineHealthService::class)->forTask($task->fresh(['pipelineSteps', 'gates']), ['pm', 'dev']);

        $this->assertSame(PipelineHealthState::Running, $health['state']);
    }

    #[Test]
    public function kanban_banner_hidden_for_in_progress_task_with_active_running_agent(): void
    {
        config(['queue.default' => 'database', 'maestro.internal_pipeline_enabled' => true]);

        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
        ]);

        PipelineStep::factory()->create([
            'task_id' => $task->id,
            'role' => 'dev',
            'status' => PipelineStepStatus::Running,
            'started_at' => now(),
        ]);

        $banner = app(PipelineHealthService::class)->kanbanWorkerBanner($project->fresh());

        $this->assertFalse($banner['show']);
    }
}
