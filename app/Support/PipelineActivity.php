<?php

namespace App\Support;

use App\Enums\PipelineStepStatus;
use App\Enums\TaskStatus;
use App\Models\PipelineStep;
use App\Models\Task;

class PipelineActivity
{
    public static function roleMessage(string $agentType): string
    {
        return match ($agentType) {
            'pm' => 'Analyse du besoin et rédaction des specs produit…',
            'ux' => 'Conception UX et wireframes textuels…',
            'tech_lead' => 'Architecture technique et plan d\'implémentation…',
            'security' => 'Revue sécurité et surface d\'attaque…',
            'dev' => 'Implémentation du code (Claude Code)…',
            'qa' => 'Scénarios de test et validation qualité…',
            'pr_expert' => 'Rédaction de la description de pull request…',
            'doc' => 'Mise à jour de la documentation…',
            default => 'Traitement en cours…',
        };
    }

    public static function shouldPoll(Task $task): bool
    {
        if ($task->status === TaskStatus::InProgress) {
            return true;
        }

        if ($task->relationLoaded('pipelineSteps')) {
            return $task->pipelineSteps->contains(
                fn (PipelineStep $run) => in_array($run->status, [PipelineStepStatus::Running, PipelineStepStatus::Pending], true)
            );
        }

        return $task->pipelineSteps()
            ->whereIn('status', [PipelineStepStatus::Running, PipelineStepStatus::Pending])
            ->exists();
    }

    public static function runningRun(Task $task): ?PipelineStep
    {
        if (! $task->relationLoaded('pipelineSteps')) {
            $task->load('pipelineSteps');
        }

        return $task->pipelineSteps->first(
            fn (PipelineStep $run) => $run->status === PipelineStepStatus::Running
        );
    }

    public static function pendingRun(Task $task): ?PipelineStep
    {
        if (! $task->relationLoaded('pipelineSteps')) {
            $task->load('pipelineSteps');
        }

        return $task->pipelineSteps->first(
            fn (PipelineStep $run) => $run->status === PipelineStepStatus::Pending
        );
    }

    public static function formatDuration(?PipelineStep $run): ?string
    {
        if (! $run?->started_at) {
            return null;
        }

        $end = $run->completed_at ?? now();

        return $run->started_at->diffForHumans($end, true, true, 2);
    }

    public static function currentPipelineRoleSlug(Task $task): ?string
    {
        if (is_string($task->current_role) && $task->current_role !== '') {
            return $task->current_role;
        }

        return self::runningRun($task)?->role;
    }

    /**
     * Échec bloquant : ignore les runs failed déjà rattrapés par un succès ou un retry en cours.
     */
    public static function blockingFailedRun(Task $task): ?PipelineStep
    {
        if (! $task->relationLoaded('pipelineSteps')) {
            $task->load('pipelineSteps');
        }

        if ($task->status === TaskStatus::Failed) {
            return $task->pipelineSteps->first(
                fn (PipelineStep $run) => $run->status === PipelineStepStatus::Failed
            );
        }

        $failedRuns = $task->pipelineSteps
            ->where('status', PipelineStepStatus::Failed)
            ->sortBy('id');

        foreach ($failedRuns as $failed) {
            $laterRuns = $task->pipelineSteps->filter(
                fn (PipelineStep $run) => $run->role === $failed->role && $run->id > $failed->id
            );

            $recovered = $laterRuns->contains(
                fn (PipelineStep $run) => in_array($run->status, [
                    PipelineStepStatus::Completed,
                    PipelineStepStatus::Skipped,
                    PipelineStepStatus::Running,
                    PipelineStepStatus::Pending,
                ], true)
            );

            if (! $recovered) {
                return $failed;
            }
        }

        return null;
    }
}
