<?php

namespace Tests\Feature\Controllers\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineCockpitControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user, 'owner')->create();
        $this->task = Task::factory()->for($this->project)->create(['status' => 'in_progress']);
    }

    public function test_show_renders_cockpit_page(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('projects.tasks.cockpit', [$this->project, $this->task]));

        $response->assertOk();
        $response->assertViewHas('task', $this->task);
        $response->assertViewHas('project', $this->project);
    }

    public function test_show_requires_authorization(): void
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $response = $this->get(route('projects.tasks.cockpit', [$this->project, $this->task]));

        $response->assertForbidden();
    }

    public function test_show_requires_authentication(): void
    {
        $response = $this->get(route('projects.tasks.cockpit', [$this->project, $this->task]));

        $response->assertRedirect('/login');
    }

    public function test_show_with_wrong_project_fails(): void
    {
        $this->actingAs($this->user);

        $otherProject = Project::factory()->create();
        $otherTask = Task::factory()->for($otherProject)->create();

        $response = $this->get(route('projects.tasks.cockpit', [$this->project, $otherTask]));

        $response->assertNotFound();
    }
}
