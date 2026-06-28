<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectRole>
 */
class ProjectRoleFactory extends Factory
{
    protected $model = ProjectRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'role' => fake()->randomElement(array_column(\App\Enums\PipelineRoleSlug::cases(), 'value')),
            'is_active' => true,
            'model' => 'claude-sonnet-4-6',
            'system_prompt' => 'Prompt par défaut.',
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
