<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'github_repo' => fake()->userName().'/'.fake()->slug(2),
            'github_branch' => 'main',
            'context' => [
                'stack' => ['Laravel', 'Livewire', 'Tailwind'],
                'conventions' => [],
                'modules' => [],
                'design_system' => [],
                'constraints' => [],
            ],
            'pipeline_config' => config('maestro.default_pipelines'),
            'gate_config' => config('maestro.default_gate_config'),
            'default_modes' => config('maestro.default_modes'),
            'model_config' => config('maestro.default_models'),
            'status' => ProjectStatus::Active,
        ];
    }
}
