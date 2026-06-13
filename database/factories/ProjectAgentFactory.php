<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectAgent>
 */
class ProjectAgentFactory extends Factory
{
    protected $model = ProjectAgent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'agent_type' => fake()->randomElement(array_column(\App\Enums\AgentType::cases(), 'value')),
            'is_active' => true,
            'model' => 'claude-sonnet-4-6',
            'system_prompt' => 'Prompt par défaut.',
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
