<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\LocalFakeProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalFakeProjectSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_sandbox_project_for_thomas(): void
    {
        User::factory()->create(['email' => LocalFakeProjectSeeder::USER_EMAIL]);

        $this->seed(LocalFakeProjectSeeder::class);

        $project = Project::query()->where('uuid', LocalFakeProjectSeeder::PROJECT_UUID)->first();

        $this->assertNotNull($project);
        $this->assertSame('Sandbox Local', $project->name);
        $this->assertSame(5, $project->tasks()->count());
        $this->assertTrue(
            $project->tasks()->where('status', TaskStatus::WaitingHermes)->exists()
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        User::factory()->create(['email' => LocalFakeProjectSeeder::USER_EMAIL]);

        $this->seed(LocalFakeProjectSeeder::class);
        $this->seed(LocalFakeProjectSeeder::class);

        $this->assertSame(1, Project::query()->where('uuid', LocalFakeProjectSeeder::PROJECT_UUID)->count());
        $this->assertSame(5, Task::query()->where('project_id', Project::first()->id)->count());
    }
}
