<?php

namespace App\Services;

use App\Enums\AgentRunStatus;
use App\Enums\PipelineHealthState;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\PipelineActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class PipelineHealthService
{
    /**
     * @return array{
     *     state: PipelineHealthState,
     *     title: string,
     *     message: string,
     *     progress: int,
     *     completed_count: int,
     *     total_steps: int,
     *     current_step: int,
     *     current_agent: ?string,
     *     tone: string,
     * }
     */
    public function forTask(Task $task, ?array $pipeline = null): array
    {
        $orchestrator = app(OrchestratorService::class);
        $pipeline ??= $orchestrator->getPipelineForTask($task);
        $totalSteps = count($pipeline);

        $task->loadMissing(['agentRuns', 'gates']);

        $completedCount = $task->agentRuns
            ->whereIn('status', [AgentRunStatus::Completed, AgentRunStatus::Skipped])
            ->count();

        $progress = $totalSteps > 0
            ? (int) round(($completedCount / $totalSteps) * 100)
            : 0;

        $pendingGate = $task->gates->where('status', 'pending')->first();
        $pendingRun = $task->agentRuns->first(fn ($run) => $run->status === AgentRunStatus::Pending);
        $runningRun = PipelineActivity::runningRun($task);
        $failedRun = $task->agentRuns->first(fn ($run) => $run->status === AgentRunStatus::Failed);
        $currentAgent = PipelineActivity::currentAgentType($task)
            ?? $pendingRun?->agent_type
            ?? $runningRun?->agent_type;

        $currentStep = $currentAgent
            ? max(1, (int) array_search($currentAgent, $pipeline, true) + 1)
            : min($completedCount + 1, max(1, $totalSteps));

        if ($task->status === TaskStatus::Done) {
            return $this->build(
                PipelineHealthState::Completed,
                'Pipeline terminée',
                'Tous les agents ont complété leur travail.',
                100,
                $completedCount,
                $totalSteps,
                $totalSteps,
                null,
                'success',
            );
        }

        if ($task->status === TaskStatus::Failed || $failedRun) {
            return $this->build(
                PipelineHealthState::Failed,
                'Pipeline en échec',
                $failedRun?->error_message ?? 'Un agent a échoué. Consultez le détail ci-dessous.',
                $progress,
                $completedCount,
                $totalSteps,
                $currentStep,
                $failedRun?->agent_type ?? $currentAgent,
                'danger',
            );
        }

        if ($pendingGate) {
            return $this->build(
                PipelineHealthState::WaitingGate,
                'Validation requise',
                'Gate « '.$pendingGate->gate_type->value.' » — approuvez ou rejetez pour continuer.',
                $progress,
                $completedCount,
                $totalSteps,
                $currentStep,
                $currentAgent,
                'warning',
            );
        }

        if ($runningRun && $currentAgent) {
            $label = config("maestro.agent_labels.{$currentAgent}.name", $currentAgent);

            return $this->build(
                PipelineHealthState::Running,
                "{$label} en cours",
                PipelineActivity::agentMessage($currentAgent),
                $progress,
                $completedCount,
                $totalSteps,
                $currentStep,
                $currentAgent,
                'primary',
            );
        }

        if ($pendingRun && $currentAgent) {
            $label = config("maestro.agent_labels.{$currentAgent}.name", $currentAgent);

            if ($this->isWorkerBlocked($task)) {
                return $this->build(
                    PipelineHealthState::BlockedWorker,
                    'Pipeline bloquée',
                    $this->blockedWorkerMessage(),
                    $progress,
                    $completedCount,
                    $totalSteps,
                    $currentStep,
                    $currentAgent,
                    'danger',
                );
            }

            return $this->build(
                PipelineHealthState::Queued,
                "{$label} en file d'attente",
                'Le job est dispatché — l\'agent va démarrer dans quelques secondes.',
                $progress,
                $completedCount,
                $totalSteps,
                $currentStep,
                $currentAgent,
                'primary',
            );
        }

        if ($task->status === TaskStatus::InProgress && $this->isWorkerBlocked($task)) {
            return $this->build(
                PipelineHealthState::BlockedWorker,
                'Pipeline bloquée',
                $this->blockedWorkerMessage(),
                $progress,
                $completedCount,
                $totalSteps,
                $currentStep,
                $currentAgent,
                'danger',
            );
        }

        if ($task->status === TaskStatus::Backlog) {
            return $this->build(
                PipelineHealthState::NotStarted,
                'Prêt à démarrer',
                'Cliquez sur « Lancer la pipeline » pour démarrer le premier agent.',
                0,
                0,
                $totalSteps,
                1,
                $pipeline[0] ?? null,
                'muted',
            );
        }

        return $this->build(
            PipelineHealthState::Queued,
            'Pipeline en cours',
            'En attente du prochain agent…',
            $progress,
            $completedCount,
            $totalSteps,
            $currentStep,
            $currentAgent,
            'primary',
        );
    }

    public function isHorizonRunning(): bool
    {
        if (config('queue.default') === 'sync') {
            return true;
        }

        try {
            $masters = app(MasterSupervisorRepository::class)->all();

            if ($masters === [] || $masters === null) {
                return false;
            }

            return collect($masters)->contains(fn ($master) => $master->status !== 'paused');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{tone: string, title: string, message: string, show: bool}
     */
    public function kanbanWorkerBanner(Project $project): array
    {
        if (config('queue.default') === 'sync') {
            return ['tone' => 'success', 'title' => '', 'message' => '', 'show' => false];
        }

        $inProgressCount = $project->tasks()->where('status', TaskStatus::InProgress)->count();
        $pendingJobs = $this->pendingAgentQueueSize();
        $queueDriver = config('queue.default');
        $horizonRunning = $this->isHorizonRunning();

        if ($queueDriver === 'database' && $pendingJobs > 0) {
            return [
                'tone' => 'danger',
                'title' => 'Worker queue inactif (driver database)',
                'message' => 'Vos jobs sont en base (`QUEUE_CONNECTION=database`) mais Horizon n\'écoute que Redis. Relancez `./start-dev` ou lancez `php artisan queue:work database --queue=agents,dev-agent,default`.',
                'show' => true,
            ];
        }

        if (! $horizonRunning && ($inProgressCount > 0 || $pendingJobs > 0)) {
            return [
                'tone' => 'danger',
                'title' => 'Horizon inactif — pipelines bloquées',
                'message' => 'Des tâches attendent un worker. Lancez `./start-dev` ou `php artisan horizon` (driver redis).',
                'show' => true,
            ];
        }

        if ($horizonRunning && $inProgressCount > 0) {
            return [
                'tone' => 'success',
                'title' => 'Pipeline active',
                'message' => $inProgressCount.' tâche(s) en cours — worker actif.',
                'show' => true,
            ];
        }

        if ($queueDriver === 'database' && ! $horizonRunning && $inProgressCount === 0) {
            return [
                'tone' => 'warning',
                'title' => 'Queue database',
                'message' => 'Driver database : utilisez `./start-dev` (queue:work) ou passez `QUEUE_CONNECTION=redis` pour Horizon.',
                'show' => true,
            ];
        }

        if (! $horizonRunning && $queueDriver === 'redis') {
            return [
                'tone' => 'warning',
                'title' => 'Horizon non démarré',
                'message' => 'Pour exécuter les pipelines, lancez `composer dev` ou `php artisan horizon` dans un terminal.',
                'show' => true,
            ];
        }

        return ['tone' => 'success', 'title' => '', 'message' => '', 'show' => false];
    }

    private function isWorkerBlocked(Task $task): bool
    {
        if (config('queue.default') === 'sync') {
            return false;
        }

        $hasPendingRuns = $task->agentRuns->contains(
            fn ($run) => $run->status === AgentRunStatus::Pending
        );

        $pendingJobs = $this->pendingAgentQueueSize();

        if (config('queue.default') === 'database') {
            if ($pendingJobs > 0) {
                return true;
            }

            return $hasPendingRuns && $this->hasStalePendingRuns($task);
        }

        if ($this->isHorizonRunning()) {
            return false;
        }

        return $hasPendingRuns || $pendingJobs > 0;
    }

    private function blockedWorkerMessage(): string
    {
        if (config('queue.default') === 'database') {
            return 'Jobs en attente dans la base (`QUEUE_CONNECTION=database`). Relancez `./start-dev` ou exécutez `php artisan queue:work database --queue=agents,dev-agent,default`. Horizon seul ne suffit pas.';
        }

        return 'Horizon n\'est pas démarré ou n\'écoute pas Redis. Lancez `./start-dev` ou `php artisan horizon`.';
    }

    private function hasStalePendingRuns(Task $task): bool
    {
        return $task->agentRuns->contains(
            fn ($run) => $run->status === AgentRunStatus::Pending
                && $run->created_at?->lt(now()->subSeconds(30))
        );
    }

    private function pendingAgentQueueSize(): int
    {
        $driver = config('queue.default');

        if ($driver === 'database') {
            return (int) DB::table('jobs')
                ->whereIn('queue', ['agents', 'dev-agent'])
                ->count();
        }

        if ($driver !== 'redis') {
            return 0;
        }

        try {
            $connection = config('queue.connections.redis.connection', 'default');
            $prefix = config('horizon.prefix', '');

            return (int) Redis::connection($connection)->llen($prefix.'queues:agents')
                + (int) Redis::connection($connection)->llen($prefix.'queues:dev-agent');
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array{
     *     state: PipelineHealthState,
     *     title: string,
     *     message: string,
     *     progress: int,
     *     completed_count: int,
     *     total_steps: int,
     *     current_step: int,
     *     current_agent: ?string,
     *     tone: string,
     * }
     */
    private function build(
        PipelineHealthState $state,
        string $title,
        string $message,
        int $progress,
        int $completedCount,
        int $totalSteps,
        int $currentStep,
        ?string $currentAgent,
        string $tone,
    ): array {
        return [
            'state' => $state,
            'title' => $title,
            'message' => $message,
            'progress' => $progress,
            'completed_count' => $completedCount,
            'total_steps' => $totalSteps,
            'current_step' => $currentStep,
            'current_agent' => $currentAgent,
            'tone' => $tone,
        ];
    }
}
