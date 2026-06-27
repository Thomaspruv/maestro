<?php

namespace App\Services\Mcp;

use App\Enums\AgentRunStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\AgentOutputCondenser;

class HermesTaskPresenter
{
    public function __construct(
        private readonly AgentOutputCondenser $condenser,
    ) {}

    public function isAwaitingHermes(Task $task): bool
    {
        if ($task->status !== TaskStatus::WaitingHermes) {
            return false;
        }

        return ! $this->hasActiveDevRun($task);
    }

    /**
     * @return array<string, mixed>
     */
    public function listItem(Task $task): array
    {
        $task->loadMissing(['project:id,name,uuid,github_repo,github_branch', 'agentRuns']);

        return [
            'task_id' => $task->id,
            'uuid' => $task->uuid,
            'title' => $task->title,
            'type' => $task->type->value,
            'priority' => $task->priority->value,
            'module' => $task->module,
            'ready_since' => $task->updated_at?->toIso8601String(),
            'project' => [
                'id' => $task->project_id,
                'name' => $task->project->name,
                'github_repo' => $task->project->github_repo,
                'github_branch' => $task->project->github_branch,
            ],
            'hermes_action' => 'implement_dev',
            'planning_agents_completed' => $this->completedPlanningAgents($task),
            'instruction' => $this->instruction(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailBlock(Task $task): array
    {
        $task->loadMissing(['project:id,name,uuid,github_repo,github_branch', 'agentRuns']);

        $shouldProcess = $this->isAwaitingHermes($task)
            || ($task->status === TaskStatus::InProgress
                && $task->current_agent === 'hermes'
                && ! $this->hasActiveDevRun($task));

        return [
            'should_process' => $shouldProcess,
            'action' => $shouldProcess ? 'implement_dev' : null,
            'instruction' => $shouldProcess ? $this->instruction() : null,
            'ready_since' => $task->status === TaskStatus::WaitingHermes
                ? $task->updated_at?->toIso8601String()
                : null,
            'github' => [
                'repo' => $task->project->github_repo,
                'branch' => $task->github_branch ?? $task->project->github_branch,
            ],
            'planning_agents_completed' => $this->completedPlanningAgents($task),
            'specs_preview' => $this->specsPreview($task),
        ];
    }

    public function instruction(): string
    {
        return 'Implémenter le code selon les specs des agents de planning (PM, UX, Tech Lead). '
            .'Appeler claim_hermes_task avant de commencer, puis add_agent_output avec agent_type=dev une fois terminé.';
    }

    /**
     * @return array<int, string>
     */
    public function completedPlanningAgents(Task $task): array
    {
        $planningAgents = ['pm', 'ux', 'tech_lead', 'security'];

        return $task->agentRuns
            ->whereIn('agent_type', $planningAgents)
            ->whereIn('status', [AgentRunStatus::Completed, AgentRunStatus::Skipped])
            ->pluck('agent_type')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function specsPreview(Task $task): array
    {
        $preview = [];

        foreach (['tech_lead', 'ux', 'pm'] as $agentType) {
            $run = $task->agentRuns
                ->where('agent_type', $agentType)
                ->where('status', AgentRunStatus::Completed)
                ->sortByDesc('id')
                ->first();

            if ($run === null) {
                continue;
            }

            $preview[$agentType] = $this->condenser->condense($run->edited_output ?? $run->output ?? '');
        }

        return $preview;
    }

    private function hasActiveDevRun(Task $task): bool
    {
        if (! $task->relationLoaded('agentRuns')) {
            return $task->agentRuns()
                ->where('agent_type', 'dev')
                ->whereIn('status', [
                    AgentRunStatus::Completed,
                    AgentRunStatus::Running,
                    AgentRunStatus::Pending,
                ])
                ->exists();
        }

        return $task->agentRuns
            ->where('agent_type', 'dev')
            ->whereIn('status', [
                AgentRunStatus::Completed,
                AgentRunStatus::Running,
                AgentRunStatus::Pending,
            ])
            ->isNotEmpty();
    }
}
