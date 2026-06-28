<?php

namespace App\Services;

use App\Enums\PipelineStepStatus;
use App\Enums\PipelineHealthState;
use App\Enums\TaskStatus;
use App\Models\PipelineStep;
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
     *     current_role: ?string,
     *     tone: string,
     * }
     */
    public function forTask(Task $task, ?array $pipeline = null): array
    {
        $orchestrator = app(OrchestratorService::class);
        $pipeline ??= $orchestrator->getPipelineForTask($task);
        $totalSteps = count($pipeline);

        $task->loadMissing(['pipelineSteps', 'gates']);

        $completedCount = $task->pipelineSteps
            ->whereIn('status', [PipelineStepStatus::Completed, PipelineStepStatus::Skipped])
            ->count();

        $progress = $totalSteps > 0
            ? (int) round(($completedCount / $totalSteps) * 100)
            : 0;

        $pendingGate = $task->gates->where('status', 'pending')->first();
        $pendingRun = $task->pipelineSteps->first(fn ($run) => $run->status === PipelineStepStatus::Pending);
        $runningRun = PipelineActivity::runningRun($task);
        $failedRun = PipelineActivity::blockingFailedRun($task);
        $currentAgent = PipelineActivity::currentPipelineRoleSlug($task)
            ?? $pendingRun?->role
            ?? $runningRun?->role;

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
                $failedRun?->role ?? $currentAgent,
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

        $staleRunning = $this->findStaleRunningRun($task, $runningRun);

        if ($staleRunning) {
            $label = config("maestro.role_labels.{$staleRunning->role}.name", $staleRunning->role);

            return $this->build(
                PipelineHealthState::Failed,
                'Pipeline interrompue',
                "L'agent {$label} semble bloqué (job crashé sans mise à jour). Relancez l'agent ou consultez les logs.",
                $progress,
                $completedCount,
                $totalSteps,
                $currentStep,
                $staleRunning->role,
                'danger',
            );
        }

        if ($runningRun && $currentAgent) {
            $label = config("maestro.role_labels.{$currentAgent}.name", $currentAgent);

            return $this->build(
                PipelineHealthState::Running,
                "{$label} en cours",
                PipelineActivity::roleMessage($currentAgent),
                $progress,
                $completedCount,
                $totalSteps,
                $currentStep,
                $currentAgent,
                'primary',
            );
        }

        if ($pendingRun && $currentAgent) {
            $label = config("maestro.role_labels.{$currentAgent}.name", $currentAgent);

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

        if ($task->status === TaskStatus::WaitingHermes) {
            return $this->build(
                PipelineHealthState::WaitingHermes,
                'En attente d\'Hermes',
                'Hermes récupère cette tâche via son cron MCP.',
                $progress,
                $completedCount,
                $totalSteps,
                $currentStep,
                'hermes',
                'primary',
            );
        }

        if ($task->status === TaskStatus::Backlog) {
            return $this->build(
                PipelineHealthState::NotStarted,
                'Prêt à démarrer',
                'Cliquez sur « Démarrer les agents » pour démarrer le premier agent.',
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
     * @return array{tone: string, title: string, message: string, show: bool, show_horizon_link: bool}
     */
    public function kanbanWorkerBanner(Project $project): array
    {
        $hidden = [
            'tone' => 'success',
            'title' => '',
            'message' => '',
            'show' => false,
            'show_horizon_link' => false,
        ];

        if (config('queue.default') === 'sync') {
            return $hidden;
        }

        if (! $this->projectNeedsWorker($project)) {
            return $hidden;
        }

        $queueDriver = config('queue.default');

        if ($queueDriver === 'database') {
            return [
                'tone' => 'danger',
                'title' => 'Worker queue inactif',
                'message' => 'Des jobs attendent un worker. Lancez `./start-dev` ou `php artisan queue:work database --queue=agents,REMOVED_DEV_AGENT,default`.',
                'show' => true,
                'show_horizon_link' => false,
            ];
        }

        if ($queueDriver === 'redis' && ! $this->isHorizonRunning()) {
            return [
                'tone' => 'danger',
                'title' => 'Horizon inactif — pipelines bloquées',
                'message' => 'Des tâches attendent un worker. Lancez `./start-dev` ou `php artisan horizon`.',
                'show' => true,
                'show_horizon_link' => true,
            ];
        }

        return $hidden;
    }

    private function projectNeedsWorker(Project $project): bool
    {
        if (config('queue.default') === 'database' && $this->isDatabaseQueueStalled()) {
            return true;
        }

        if (config('queue.default') === 'redis' && $this->pendingAgentQueueSize() > 0 && ! $this->isHorizonRunning()) {
            return true;
        }

        $inProgressTasks = $project->tasks()
            ->where('status', TaskStatus::InProgress)
            ->with('pipelineSteps')
            ->get();

        foreach ($inProgressTasks as $task) {
            if ($this->hasStalePendingRuns($task)) {
                return true;
            }

            $runningRun = PipelineActivity::runningRun($task);

            if ($this->findStaleRunningRun($task, $runningRun)) {
                return true;
            }
        }

        return false;
    }

    private function isWorkerBlocked(Task $task): bool
    {
        if (config('queue.default') === 'sync') {
            return false;
        }

        $hasPendingRuns = $task->pipelineSteps->contains(
            fn ($run) => $run->status === PipelineStepStatus::Pending
        );

        if (config('queue.default') === 'database') {
            if ($this->isDatabaseQueueStalled()) {
                return true;
            }

            return $hasPendingRuns && $this->hasStalePendingRuns($task);
        }

        if ($this->isHorizonRunning()) {
            return false;
        }

        return $hasPendingRuns
            || $this->pendingAgentQueueSize() > 0;
    }

    /**
     * Jobs en attente sans worker actif (driver database).
     */
    private function isDatabaseQueueStalled(): bool
    {
        if (config('queue.default') !== 'database') {
            return false;
        }

        if ($this->pendingUnreservedQueueSize() === 0) {
            return false;
        }

        return ! $this->hasActiveDatabaseWorker();
    }

    private function hasActiveDatabaseWorker(): bool
    {
        return DB::table('jobs')
            ->whereIn('queue', ['roles', 'REMOVED_DEV_AGENT', 'default'])
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '>=', now()->subSeconds(120)->timestamp)
            ->exists();
    }

    private function pendingUnreservedQueueSize(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        return (int) DB::table('jobs')
            ->whereIn('queue', ['roles', 'REMOVED_DEV_AGENT', 'default'])
            ->whereNull('reserved_at')
            ->count();
    }

    private function blockedWorkerMessage(): string
    {
        if (config('queue.default') === 'database') {
            return 'Aucun worker ne consomme la queue. Lancez `composer dev` ou `./start-dev.sh` dans un terminal et laissez-le ouvert pendant que vous travaillez.';
        }

        return 'Horizon n\'est pas démarré. Lancez `composer dev` ou `php artisan horizon`.';
    }

    private function hasStalePendingRuns(Task $task): bool
    {
        return $task->pipelineSteps->contains(
            fn ($run) => $run->status === PipelineStepStatus::Pending
                && $run->created_at?->lt(now()->subSeconds(30))
        );
    }

    private function findStaleRunningRun(Task $task, ?PipelineStep $runningRun): ?PipelineStep
    {
        if (! $runningRun?->started_at) {
            return null;
        }

        if ($runningRun->started_at->gt(now()->subSeconds($this->staleRunningThresholdSeconds()))) {
            return null;
        }

        if ($this->pendingAgentQueueSize() > 0) {
            return null;
        }

        if ($this->hasActiveDatabaseWorker()) {
            return null;
        }

        if ($this->isHorizonRunning()) {
            return null;
        }

        return $runningRun;
    }

    private function staleRunningThresholdSeconds(): int
    {
        $apiTimeout = (int) config('maestro.anthropic_timeout', 180);
        $devTimeout = (int) config('maestro.dev_claude_timeout', 900);

        return max($apiTimeout, $devTimeout) + 120;
    }

    private function pendingAgentQueueSize(): int
    {
        $driver = config('queue.default');

        if ($driver === 'database') {
            return (int) DB::table('jobs')
                ->whereIn('queue', ['roles', 'REMOVED_DEV_AGENT'])
                ->count();
        }

        if ($driver !== 'redis') {
            return 0;
        }

        try {
            $connection = config('queue.connections.redis.connection', 'default');
            $prefix = config('horizon.prefix', '');

            return (int) Redis::connection($connection)->llen($prefix.'queues:agents')
                + (int) Redis::connection($connection)->llen($prefix.'queues:REMOVED_DEV_AGENT');
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
     *     current_role: ?string,
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
            'current_role' => $currentAgent,
            'tone' => $tone,
        ];
    }
}
