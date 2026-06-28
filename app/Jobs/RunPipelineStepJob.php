<?php

namespace App\Jobs;

use App\Enums\PipelineStepStatus;
use App\Enums\TaskStatus;
use App\Events\PipelineStepCostRecorded;
use App\Events\PipelineStepUpdated;
use App\Models\PipelineStep;
use App\Models\Task;
use App\Services\NotificationService;
use App\Services\OrchestratorService;
use App\Services\PipelineRoleCapabilities;
use App\Services\PipelineStepRunnerService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunPipelineStepJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 120;

    public function tries(): int
    {
        return (int) config('maestro.pipeline_step_job_tries', 2);
    }

    public function backoff(): array
    {
        return [(int) config('maestro.pipeline_step_job_retry_delay', 15)];
    }

    public function __construct(
        public readonly Task $task,
        public readonly string $role,
        public readonly ?string $feedback = null,
        public readonly bool $skipAdvance = false,
        public ?int $pipelineStepId = null,
    ) {
        $this->onQueue(PipelineRoleCapabilities::queue($this->role));
        $this->timeout = (int) config('maestro.anthropic_timeout', 180) + 120;
    }

    public function handle(
        PipelineStepRunnerService $runner,
        OrchestratorService $orchestrator,
        NotificationService $notifications,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->pipelineStepId) {
            $step = PipelineStep::query()->findOrFail($this->pipelineStepId);
            $step->update([
                'status' => PipelineStepStatus::Running,
                'error_message' => null,
                'input' => $this->buildInput(),
                'model' => $step->model ?: $this->resolveModel(),
                'started_at' => $step->started_at ?? now(),
            ]);
        } else {
            $step = PipelineStep::create([
                'task_id' => $this->task->id,
                'role' => $this->role,
                'status' => PipelineStepStatus::Running,
                'input' => $this->buildInput(),
                'model' => $this->resolveModel(),
                'started_at' => now(),
                'attempt' => 1,
            ]);
            $this->pipelineStepId = $step->id;
        }

        $this->task->update([
            'status' => TaskStatus::InProgress,
            'current_role' => $this->role,
        ]);

        broadcast(new PipelineStepUpdated($step->fresh()));

        try {
            $result = $runner->run($step);

            $step->update([
                'status' => PipelineStepStatus::Completed,
                'output' => $result->output,
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'cached_tokens' => $result->cachedTokens,
                'cost' => $result->cost,
                'completed_at' => now(),
            ]);

            $this->task->increment('actual_cost', $result->cost);
            $step = $step->fresh();
            broadcast(new PipelineStepUpdated($step));
            broadcast(new PipelineStepCostRecorded($step));

            if (PipelineRoleCapabilities::postAction($this->role) === 'open_pr') {
                OpenPullRequestJob::dispatch($this->task->fresh());
            }

            if (! $this->skipAdvance) {
                $orchestrator->advance($this->task->fresh());
            }
        } catch (Throwable $e) {
            if ($this->isTransientFailure($e)) {
                Log::warning('RunPipelineStepJob transient failure, will retry', [
                    'task_id' => $this->task->id,
                    'role' => $this->role,
                    'attempt' => $this->attempts(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $this->handleFailure($step, $notifications, $e);
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

    private function handleFailure(PipelineStep $step, NotificationService $notifications, Throwable $e): void
    {
        Log::error('RunPipelineStepJob failed', [
            'task_id' => $this->task->id,
            'role' => $this->role,
            'attempt' => $step->attempt,
            'error' => $e->getMessage(),
        ]);

        $step->update([
            'status' => PipelineStepStatus::Failed,
            'error_message' => $e->getMessage(),
            'completed_at' => now(),
        ]);

        $this->task->update([
            'status' => TaskStatus::Failed,
            'current_role' => null,
        ]);

        broadcast(new PipelineStepUpdated($step->fresh()));
        $notifications->notifyFailure($this->task, $step);
    }

    public function failed(?Throwable $exception): void
    {
        if (! $this->pipelineStepId) {
            return;
        }

        $step = PipelineStep::query()->find($this->pipelineStepId);

        if (! $step || $step->status !== PipelineStepStatus::Running) {
            return;
        }

        $step->update([
            'status' => PipelineStepStatus::Failed,
            'error_message' => $exception?->getMessage() ?? 'Job interrompu par la queue.',
            'completed_at' => now(),
        ]);

        $this->task->update([
            'status' => TaskStatus::Failed,
            'current_role' => null,
        ]);

        broadcast(new PipelineStepUpdated($step->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInput(): array
    {
        $inputs = $this->task->pipelineSteps()
            ->where('status', PipelineStepStatus::Completed)
            ->get()
            ->mapWithKeys(fn (PipelineStep $step) => [
                $step->role => $step->edited_output ?? $step->output,
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

        return PipelineRoleCapabilities::resolveModel($this->role, $this->task->project);
    }
}
