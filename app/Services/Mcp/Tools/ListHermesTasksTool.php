<?php

namespace App\Services\Mcp\Tools;

use App\Enums\PipelineStepStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\HermesTaskPresenter;
use Illuminate\Database\Eloquent\Builder;

class ListHermesTasksTool implements McpTool
{
    public function __construct(
        private readonly HermesTaskPresenter $presenter,
    ) {}

    public function name(): string
    {
        return 'list_hermes_tasks';
    }

    public function description(): string
    {
        return 'Liste toutes les tâches prêtes pour Hermes (statut waiting_hermes, sans run dev actif), tous projets confondus. À appeler depuis le cron Hermes.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Nombre maximum de tâches retournées (défaut 10)',
                ],
            ],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        $limit = min(max((int) ($arguments['limit'] ?? 10), 1), 50);

        $tasks = Task::query()
            ->where('status', TaskStatus::WaitingHermes)
            ->whereHas(
                'project',
                fn (Builder $query) => $query
                    ->where('user_id', $user->id)
                    ->where('status', ProjectStatus::Active),
            )
            ->whereDoesntHave(
                'pipelineSteps',
                fn (Builder $query) => $query
                    ->where('role', 'dev')
                    ->whereIn('status', [
                        PipelineStepStatus::Completed,
                        PipelineStepStatus::Running,
                        PipelineStepStatus::Pending,
                    ]),
            )
            ->with(['project:id,name,uuid,github_repo,github_branch', 'pipelineSteps'])
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $items = $tasks
            ->filter(fn (Task $task) => $this->presenter->isAwaitingHermes($task))
            ->map(fn (Task $task) => $this->presenter->listItem($task))
            ->values()
            ->all();

        return [
            'tasks' => $items,
            'count' => count($items),
            'polling_hint' => 'Traiter tasks[0] en priorité. Workflow : claim_hermes_task → implémenter → record_step_output(dev).',
        ];
    }
}
