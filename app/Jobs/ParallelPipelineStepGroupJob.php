<?php

namespace App\Jobs;

use App\Models\Task;
use App\Services\OrchestratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;

class ParallelPipelineStepGroupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    /**
     * @param  array<int, string>  $roles
     * @param  array<string, int>  $pipelineStepIds
     */
    public function __construct(
        public readonly Task $task,
        public readonly array $roles,
        public readonly array $pipelineStepIds = [],
    ) {
        $this->onQueue('roles');
    }

    public function handle(OrchestratorService $orchestrator): void
    {
        $jobs = collect($this->roles)
            ->map(fn (string $role) => new RunPipelineStepJob(
                task: $this->task,
                role: $role,
                skipAdvance: true,
                pipelineStepId: $this->pipelineStepIds[$role] ?? null,
            ))
            ->all();

        $taskId = $this->task->id;

        Bus::batch($jobs)
            ->name("task-{$taskId}-parallel")
            ->onQueue('roles')
            ->allowFailures(false)
            ->finally(function () use ($orchestrator, $taskId): void {
                $task = Task::query()->findOrFail($taskId);
                $orchestrator->advance($task);
            })
            ->dispatch();
    }
}
