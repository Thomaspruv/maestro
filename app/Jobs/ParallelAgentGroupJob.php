<?php

namespace App\Jobs;

use App\Models\Task;
use App\Services\OrchestratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;

class ParallelAgentGroupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    /**
     * @param  array<int, string>  $agentTypes
     */
    public function __construct(
        public readonly Task $task,
        public readonly array $agentTypes,
    ) {
        $this->onQueue('agents');
    }

    public function handle(OrchestratorService $orchestrator): void
    {
        $jobs = collect($this->agentTypes)
            ->map(fn (string $agentType) => new RunAgentJob(
                task: $this->task,
                agentType: $agentType,
                skipAdvance: true,
            ))
            ->all();

        $taskId = $this->task->id;

        Bus::batch($jobs)
            ->name("task-{$taskId}-parallel")
            ->allowFailures(false)
            ->finally(function () use ($orchestrator, $taskId): void {
                $task = Task::query()->findOrFail($taskId);
                $orchestrator->advance($task);
            })
            ->dispatch();
    }
}
