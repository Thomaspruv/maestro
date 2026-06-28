<?php

namespace App\Services\Mcp\Tools;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\HermesTaskPresenter;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\ResolvesMcpResources;
use Illuminate\Support\Facades\DB;

class ClaimHermesTaskTool implements McpTool
{
    use ResolvesMcpResources;

    public function __construct(
        private readonly HermesTaskPresenter $presenter,
    ) {}

    public function name(): string
    {
        return 'claim_hermes_task';
    }

    public function description(): string
    {
        return 'Réserve une tâche pour Hermes (anti-doublon cron). Passe la tâche en in_progress et retourne le contexte de travail.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer', 'description' => 'ID de la tâche waiting_hermes à réserver'],
            ],
            'required' => ['task_id'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        if (! isset($arguments['task_id'])) {
            throw McpToolException::missing('task_id');
        }

        $taskId = (int) $arguments['task_id'];

        $task = DB::transaction(function () use ($user, $taskId): Task {
            $task = Task::query()
                ->whereKey($taskId)
                ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
                ->lockForUpdate()
                ->first();

            if ($task === null) {
                throw McpToolException::notFound('task');
            }

            if (! $this->presenter->isAwaitingHermes($task->load('pipelineSteps'))) {
                throw McpToolException::invalid(
                    'Cette tâche n\'est pas disponible pour Hermes (statut ou run dev déjà présent).',
                );
            }

            $task->update([
                'status' => TaskStatus::InProgress,
                'current_role' => 'hermes',
            ]);

            return $task->fresh(['project:id,name,uuid,github_repo,github_branch', 'pipelineSteps']);
        });

        return [
            'claimed' => true,
            'task' => $this->presenter->listItem($task),
            'hermes' => $this->presenter->detailBlock($task),
            'next_steps' => [
                'Implémenter le code dans le dépôt GitHub indiqué.',
                'Appeler record_step_output avec role=dev et un résumé du travail effectué.',
                'En cas d\'échec, remettre la tâche en waiting_hermes via update_task_status.',
            ],
        ];
    }
}
