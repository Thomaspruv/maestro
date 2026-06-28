<?php

namespace App\Services\Mcp;

use App\Enums\PipelineStepStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\PipelineOutputCondenser;

class HermesTaskPresenter
{
    public function __construct(
        private readonly PipelineOutputCondenser $condenser,
    ) {}

    public static function workflowMode(): string
    {
        return config('maestro.internal_pipeline_enabled', false)
            ? 'internal_pipeline'
            : 'hermes_only';
    }

    public static function pollingHint(): string
    {
        if (self::workflowMode() === 'hermes_only') {
            return 'Traiter tasks[0] en priorité. Workflow : claim_hermes_task → implémenter selon titre/description → record_step_output(dev) → done.';
        }

        return 'Traiter tasks[0] en priorité. Workflow : claim_hermes_task → implémenter selon specs planning → record_step_output(dev) → QA/Doc.';
    }

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
        $task->loadMissing(['project:id,name,uuid,github_repo,github_branch', 'pipelineSteps']);

        $item = [
            'task_id' => $task->id,
            'uuid' => $task->uuid,
            'title' => $task->title,
            'type' => $task->type->value,
            'priority' => $task->priority->value,
            'module' => $task->module,
            'ready_since' => $task->updated_at?->toIso8601String(),
            'workflow_mode' => self::workflowMode(),
            'project' => [
                'id' => $task->project_id,
                'name' => $task->project->name,
                'github_repo' => $task->project->github_repo,
                'github_branch' => $task->project->github_branch,
            ],
            'hermes_action' => 'implement_dev',
            'instruction' => $this->instruction(),
        ];

        if (self::workflowMode() === 'internal_pipeline') {
            $item['planning_roles_completed'] = $this->completedPlanningRoles($task);
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    public function detailBlock(Task $task): array
    {
        $task->loadMissing(['project:id,name,uuid,github_repo,github_branch', 'pipelineSteps']);

        $shouldProcess = $this->isAwaitingHermes($task)
            || ($task->status === TaskStatus::InProgress
                && $task->current_role === 'hermes'
                && ! $this->hasActiveDevRun($task));

        $block = [
            'workflow_mode' => self::workflowMode(),
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
            'specs_preview' => $this->specsPreview($task),
        ];

        if (self::workflowMode() === 'internal_pipeline') {
            $block['planning_roles_completed'] = $this->completedPlanningRoles($task);
        }

        return $block;
    }

    public function instruction(): string
    {
        if (! config('maestro.internal_pipeline_enabled', false)) {
            return 'Implémenter selon le titre et la description de la tâche (voir get_task). '
                .'Appeler claim_hermes_task avant de commencer, puis record_step_output avec role=dev une fois terminé.';
        }

        return 'Implémenter le code selon les specs de planning (PM, UX, Tech Lead). '
            .'Appeler claim_hermes_task avant de commencer, puis record_step_output avec role=dev une fois terminé.';
    }

    /**
     * @return array<int, string>
     */
    public function completedPlanningRoles(Task $task): array
    {
        $planningAgents = ['pm', 'ux', 'tech_lead', 'security'];

        return $task->pipelineSteps
            ->whereIn('role', $planningAgents)
            ->whereIn('status', [PipelineStepStatus::Completed, PipelineStepStatus::Skipped])
            ->pluck('role')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function specsPreview(Task $task): array
    {
        if (! config('maestro.internal_pipeline_enabled', false)) {
            $parts = array_filter([
                'titre' => $task->title,
                'description' => $task->description,
                'module' => $task->module,
                'type' => $task->type->value,
                'priorité' => $task->priority->value,
            ]);

            return $parts;
        }

        $preview = [];

        foreach (['tech_lead', 'ux', 'pm'] as $agentType) {
            $run = $task->pipelineSteps
                ->where('role', $agentType)
                ->where('status', PipelineStepStatus::Completed)
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
        if (! $task->relationLoaded('pipelineSteps')) {
            return $task->pipelineSteps()
                ->where('role', 'dev')
                ->whereIn('status', [
                    PipelineStepStatus::Completed,
                    PipelineStepStatus::Running,
                    PipelineStepStatus::Pending,
                ])
                ->exists();
        }

        return $task->pipelineSteps
            ->where('role', 'dev')
            ->whereIn('status', [
                PipelineStepStatus::Completed,
                PipelineStepStatus::Running,
                PipelineStepStatus::Pending,
            ])
            ->isNotEmpty();
    }
}
