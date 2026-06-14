<?php

namespace App\Support;

use App\Enums\AgentRunStatus;
use App\Enums\TaskStatus;
use App\Models\AgentRun;
use App\Models\Task;

class PipelineActivity
{
    public static function agentMessage(string $agentType): string
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

        if ($task->relationLoaded('agentRuns')) {
            return $task->agentRuns->contains(
                fn (AgentRun $run) => in_array($run->status, [AgentRunStatus::Running, AgentRunStatus::Pending], true)
            );
        }

        return $task->agentRuns()
            ->whereIn('status', [AgentRunStatus::Running, AgentRunStatus::Pending])
            ->exists();
    }

    public static function runningRun(Task $task): ?AgentRun
    {
        if (! $task->relationLoaded('agentRuns')) {
            $task->load('agentRuns');
        }

        return $task->agentRuns->first(
            fn (AgentRun $run) => $run->status === AgentRunStatus::Running
        );
    }

    public static function pendingRun(Task $task): ?AgentRun
    {
        if (! $task->relationLoaded('agentRuns')) {
            $task->load('agentRuns');
        }

        return $task->agentRuns->first(
            fn (AgentRun $run) => $run->status === AgentRunStatus::Pending
        );
    }

    public static function formatDuration(?AgentRun $run): ?string
    {
        if (! $run?->started_at) {
            return null;
        }

        $end = $run->completed_at ?? now();

        return $run->started_at->diffForHumans($end, true, true, 2);
    }

    public static function currentAgentType(Task $task): ?string
    {
        if (is_string($task->current_agent) && $task->current_agent !== '') {
            return $task->current_agent;
        }

        return self::runningRun($task)?->agent_type;
    }

    /**
     * Échec bloquant : ignore les runs failed déjà rattrapés par un succès ou un retry en cours.
     */
    public static function blockingFailedRun(Task $task): ?AgentRun
    {
        if (! $task->relationLoaded('agentRuns')) {
            $task->load('agentRuns');
        }

        if ($task->status === TaskStatus::Failed) {
            return $task->agentRuns->first(
                fn (AgentRun $run) => $run->status === AgentRunStatus::Failed
            );
        }

        $failedRuns = $task->agentRuns
            ->where('status', AgentRunStatus::Failed)
            ->sortBy('id');

        foreach ($failedRuns as $failed) {
            $laterRuns = $task->agentRuns->filter(
                fn (AgentRun $run) => $run->agent_type === $failed->agent_type && $run->id > $failed->id
            );

            $recovered = $laterRuns->contains(
                fn (AgentRun $run) => in_array($run->status, [
                    AgentRunStatus::Completed,
                    AgentRunStatus::Skipped,
                    AgentRunStatus::Running,
                    AgentRunStatus::Pending,
                ], true)
            );

            if (! $recovered) {
                return $failed;
            }
        }

        return null;
    }
}
