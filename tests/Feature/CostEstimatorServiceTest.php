<?php

namespace Tests\Feature;

use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\CostEstimatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostEstimatorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_returns_agent_breakdown(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'type' => TaskType::Feature,
        ]);

        $estimate = app(CostEstimatorService::class)->estimate($task);

        $this->assertArrayHasKey('agents', $estimate);
        $this->assertArrayHasKey('total_low', $estimate);
        $this->assertArrayHasKey('total_high', $estimate);
        $this->assertNotEmpty($estimate['agents']);
    }
}
