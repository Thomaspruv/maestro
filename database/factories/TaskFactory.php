<?php

namespace Database\Factories;

use App\Enums\PrStatus;
use App\Enums\TaskMode;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'module' => fake()->optional()->word(),
            'type' => TaskType::Feature,
            'priority' => TaskPriority::Medium,
            'status' => TaskStatus::Backlog,
            'mode' => TaskMode::Manual,
            'current_role' => null,
            'github_branch' => null,
            'github_pr_url' => null,
            'github_pr_number' => null,
            'pr_status' => PrStatus::None,
            'estimated_cost' => null,
            'actual_cost' => null,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
