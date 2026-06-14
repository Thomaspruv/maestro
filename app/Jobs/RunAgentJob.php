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
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunAgentJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 120;

    public function tries(): int
    {
        return (int) config('maestro.agent_job_tries', 2);
    }

    public function backoff(): array
    {
        return [(int) config('maestro.agent_job_retry_delay', 15)];
    }

    public function __construct(
        public readonly Task $task,
        public readonly string $agentType,
        public readonly ?string $feedback = null,
        public readonly bool $skipAdvance = false,
        public ?int $agentRunId = null,
    ) {
        $this->onQueue(AgentCapabilities::queue($this->agentType));

        if (AgentCapabilities::isDev($this->agentType)) {
            $this->timeout = (int) config('maestro.dev_claude_timeout', 900) + 60;
        } else {
            $this->timeout = (int) config('maestro.anthropic_timeout', 180) + 120;
        }
    }

    public function handle(
        AgentRunnerService $runner,
        DevAgentRunner $devRunner,
        OrchestratorService $orchestrator,
        NotificationService $notifications,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->agentRunId) {
            $run = AgentRun::query()->findOrFail($this->agentRunId);
            $run->update([
                'status' => AgentRunStatus::Running,
                'error_message' => null,
                'input' => $this->buildInput(),
                'model' => $run->model ?: $this->resolveModel(),
                'started_at' => $run->started_at ?? now(),
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
            if ($this->isTransientFailure($e)) {
                Log::warning('RunAgentJob transient failure, will retry', [
                    'task_id' => $this->task->id,
                    'agent_type' => $this->agentType,
                    'attempt' => $this->attempts(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $this->handleFailure($run, $notifications, $e);
        }
    }

    private function isTransientFailure(Throwable $e): bool
    {
        if ($this->attempts() >= $this->tries()) {
            return false;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'curl error 28')
            || str_contains($message, 'operation timed out')
            || str_contains($message, '529')
            || str_contains($message, 'overloaded')
            || str_contains($message, '429')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection refused');
    }

    private function handleFailure(AgentRun $run, NotificationService $notifications, Throwable $e): void
    {
        Log::error('RunAgentJob failed', [
            'task_id' => $this->task->id,
            'agent_type' => $this->agentType,
            'attempt' => $run->attempt,
            'error' => $e->getMessage(),
        ]);

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

    public function failed(?Throwable $exception): void
    {
        if (! $this->agentRunId) {
            return;
        }

        $run = AgentRun::query()->find($this->agentRunId);

        if (! $run || $run->status !== AgentRunStatus::Running) {
            return;
        }

        $run->update([
            'status' => AgentRunStatus::Failed,
            'error_message' => $exception?->getMessage() ?? 'Job interrompu par la queue.',
            'completed_at' => now(),
        ]);

        $this->task->update([
            'status' => TaskStatus::Failed,
            'current_agent' => null,
        ]);

        broadcast(new AgentRunUpdated($run->fresh()));
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

        return AgentCapabilities::resolveModel($this->agentType, $this->task->project);
    }
}
