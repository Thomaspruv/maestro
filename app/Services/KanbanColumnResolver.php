<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class KanbanColumnResolver
{
    /**
     * @return array<int, array{slug: string, label: string, emoji: string, hint: ?string, role: ?string}>
     */
    public function columns(): array
    {
        $order = config('maestro.kanban_column_order', []);
        $definitions = config('maestro.kanban_columns', []);
        $roleLabels = config('maestro.role_labels', []);

        return collect($order)
            ->map(function (string $slug) use ($definitions, $roleLabels) {
                $meta = $definitions[$slug] ?? [];
                $role = $meta['role'] ?? null;

                $label = $meta['label'] ?? ($role !== null
                    ? ($roleLabels[$role]['name'] ?? $role)
                    : $slug);

                $emoji = $meta['emoji'] ?? ($role !== null
                    ? ($roleLabels[$role]['emoji'] ?? '📌')
                    : '📌');

                return [
                    'slug' => $slug,
                    'label' => $label,
                    'emoji' => $emoji,
                    'hint' => $meta['hint'] ?? null,
                    'role' => $role,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function columnOrder(): array
    {
        return config('maestro.kanban_column_order', []);
    }

    public function isValidColumn(string $column): bool
    {
        return in_array($column, $this->columnOrder(), true);
    }

    public function resolveColumn(Task $task): string
    {
        $status = $task->status;

        if ($status === TaskStatus::Backlog || $status === TaskStatus::Failed) {
            return 'backlog';
        }

        if ($status === TaskStatus::Done) {
            return 'done';
        }

        if ($status === TaskStatus::WaitingHermes) {
            return 'dev';
        }

        if ($status === TaskStatus::InReview) {
            return 'qa';
        }

        if ($status === TaskStatus::InProgress) {
            $role = $task->current_role;

            if (in_array($role, ['hermes', 'dev'], true)) {
                return 'dev';
            }

            if ($role === 'tech_lead') {
                return 'test_lead';
            }

            $roleColumns = $this->roleColumnSlugs();

            if ($role !== null && isset($roleColumns[$role])) {
                return $roleColumns[$role];
            }
        }

        return 'backlog';
    }

    public function applyColumn(Task $task, string $column): void
    {
        if (! $this->isValidColumn($column)) {
            throw new InvalidArgumentException("Colonne Kanban inconnue : {$column}");
        }

        if ($column === 'backlog') {
            $task->update([
                'status' => TaskStatus::Backlog,
                'current_role' => null,
            ]);

            return;
        }

        if ($column === 'done') {
            $task->update([
                'status' => TaskStatus::Done,
                'current_role' => null,
            ]);

            return;
        }

        if ($column === 'dev') {
            $task->update([
                'status' => TaskStatus::WaitingHermes,
                'current_role' => 'hermes',
            ]);

            return;
        }

        $role = $this->roleForColumn($column);

        $task->update([
            'status' => TaskStatus::InProgress,
            'current_role' => $role,
        ]);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<string, Collection<int, Task>>
     */
    public function groupTasksByColumn(Collection $tasks): array
    {
        $grouped = collect($this->columnOrder())
            ->mapWithKeys(fn (string $slug) => [$slug => collect()])
            ->all();

        foreach ($tasks as $task) {
            $column = $this->resolveColumn($task);
            $grouped[$column]->push($task);
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    public function taskSummary(Task $task): array
    {
        return [
            'id' => $task->id,
            'uuid' => $task->uuid,
            'title' => $task->title,
            'type' => $task->type->value,
            'priority' => $task->priority->value,
            'status' => $task->status->value,
            'current_role' => $task->current_role,
            'kanban_column' => $this->resolveColumn($task),
            'sort_order' => $task->sort_order,
        ];
    }

    /**
     * @return array<string, string> role slug => kanban column slug
     */
    private function roleColumnSlugs(): array
    {
        $map = [];

        foreach (config('maestro.kanban_columns', []) as $slug => $meta) {
            if (isset($meta['role'])) {
                $map[$meta['role']] = $slug;
            }
        }

        return $map;
    }

    private function roleForColumn(string $column): string
    {
        $role = config("maestro.kanban_columns.{$column}.role");

        if (! is_string($role) || $role === '') {
            throw new InvalidArgumentException("La colonne {$column} n'est pas une colonne rôle.");
        }

        return $role;
    }
}
