<?php

namespace App\Jobs;

use App\Enums\AgentRunStatus;
use App\Enums\TaskStatus;
use App\Events\AgentRunUpdated;
use App\Models\AgentRun;
use App\Models\Task;
use App\Services\AgentCapabilities;
use App\Services\AgentRunnerService;
use App\Services\DevAgentRunner;
use App\Services\NotificationService;
use App\Services\OrchestratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunAgentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public readonly Task $task,
        public readonly string $agentType,
        public readonly ?string $feedback = null,
        public readonly bool $skipAdvance = false,
        public ?int $agentRunId = null,
    ) {
        $this->onQueue(AgentCapabilities::queue($this->agentType));
    }

    public function handle(
        AgentRunnerService $runner,
        DevAgentRunner $devRunner,
        OrchestratorService $orchestrator,
        NotificationService $notifications,
    ): void {
        if ($this->agentRunId) {
            $run = AgentRun::query()->findOrFail($this->agentRunId);
            $run->update([
                'status' => AgentRunStatus::Running,
                'error_message' => null,
            ]);
        } else {
            $run = AgentRun::create([
                'task_id' => $this->task->id,
                'agent_type' => $this->agentType,
                'status' => AgentRunStatus::Running,
                'input' => $this->buildInput(),
                'model' => $this->resolveModel(),
                'started_at' => now(),
                'attempt' => 1,
            ]);
            $this->agentRunId = $run->id;
        }

        $this->task->update([
            'status' => TaskStatus::InProgress,
            'current_agent' => $this->agentType,
        ]);

        broadcast(new AgentRunUpdated($run->fresh()));

        try {
            $result = AgentCapabilities::isDev($this->agentType)
                ? $devRunner->run($run)
                : $runner->run($run);

            $run->update([
                'status' => AgentRunStatus::Completed,
                'output' => $result->output,
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'cached_tokens' => $result->cachedTokens,
                'cost' => $result->cost,
                'completed_at' => now(),
            ]);

            $this->task->increment('actual_cost', $result->cost);
            broadcast(new AgentRunUpdated($run->fresh()));

            if (AgentCapabilities::postAction($this->agentType) === 'open_pr') {
                OpenPullRequestJob::dispatch($this->task->fresh());
            }

            if (! $this->skipAdvance) {
                $orchestrator->advance($this->task->fresh());
            }
        } catch (Throwable $e) {
            $this->handleFailure($run, $notifications, $e);
        }
    }

    private function handleFailure(AgentRun $run, NotificationService $notifications, Throwable $e): void
    {
        Log::error('RunAgentJob failed', [
            'task_id' => $this->task->id,
            'agent_type' => $this->agentType,
            'attempt' => $run->attempt,
            'error' => $e->getMessage(),
        ]);

        if ($run->attempt < 3) {
            $run->increment('attempt');
            $this->release(30);

            return;
        }

        $run->update([
            'status' => AgentRunStatus::Failed,
            'error_message' => $e->getMessage(),
            'completed_at' => now(),
        ]);

        $this->task->update([
            'status' => TaskStatus::Failed,
            'current_agent' => null,
        ]);

        broadcast(new AgentRunUpdated($run->fresh()));
        $notifications->notifyFailure($this->task, $run);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInput(): array
    {
        $inputs = $this->task->agentRuns()
            ->where('status', AgentRunStatus::Completed)
            ->get()
            ->mapWithKeys(fn (AgentRun $run) => [
                $run->agent_type => $run->edited_output ?? $run->output,
            ])
            ->toArray();

        if ($this->feedback) {
            $inputs['feedback'] = $this->feedback;
        }

        return $inputs;
    }

    private function resolveModel(): string
    {
        $this->task->loadMissing('project');
        $modelConfig = $this->task->project->model_config ?? [];

        return $modelConfig[$this->agentType]
            ?? config("maestro.default_models.{$this->agentType}")
            ?? 'claude-sonnet-4-6';
    }
}
