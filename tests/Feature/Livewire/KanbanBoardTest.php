<?php

namespace Tests\Feature\Livewire;

use App\Enums\TaskStatus;
use App\Livewire\KanbanBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KanbanBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiting_hermes_task_appears_in_hermes_column(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Tâche en attente Hermes',
            'status' => TaskStatus::WaitingHermes,
            'current_role' => 'hermes',
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Tâche agents en cours',
            'status' => TaskStatus::InProgress,
            'current_role' => 'pm',
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Tâche backlog',
            'status' => TaskStatus::Backlog,
        ]);

        Livewire::actingAs($user)
            ->test(KanbanBoard::class, ['project' => $project])
            ->assertSee('Tâche en attente Hermes')
            ->assertSee('En attente d\'Hermes')
            ->assertSee('Prêt pour le cron MCP')
            ->assertSee('Voir les specs')
            ->assertSee('Envoyer à Hermes');
    }

    public function test_sync_kanban_columns_persists_move_to_hermes(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
            'current_role' => 'pm',
            'sort_order' => 0,
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Backlog,
            'sort_order' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(KanbanBoard::class, ['project' => $project])
            ->call('syncKanbanColumns', [
                'backlog' => [
                    ['task_id' => Task::where('status', TaskStatus::Backlog)->first()->id, 'sort_order' => 0],
                ],
                'in_progress' => [],
                'waiting_hermes' => [
                    ['task_id' => $task->id, 'sort_order' => 0],
                ],
                'in_review' => [],
                'done' => [],
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingHermes->value,
            'current_role' => 'hermes',
        ]);
    }

    public function test_sync_kanban_columns_clears_hermes_agent_when_leaving_column(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::WaitingHermes,
            'current_role' => 'hermes',
        ]);

        Livewire::actingAs($user)
            ->test(KanbanBoard::class, ['project' => $project])
            ->call('syncKanbanColumns', [
                'backlog' => [
                    ['task_id' => $task->id, 'sort_order' => 0],
                ],
                'in_progress' => [],
                'waiting_hermes' => [],
                'in_review' => [],
                'done' => [],
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Backlog->value,
            'current_role' => null,
        ]);
    }
}
