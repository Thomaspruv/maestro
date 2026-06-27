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
            'current_agent' => 'hermes',
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Tâche agents en cours',
            'status' => TaskStatus::InProgress,
            'current_agent' => 'pm',
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
            ->assertSee('Démarrer les agents');
    }
}
