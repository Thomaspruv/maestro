<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\PipelineRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineRole>
 */
class PipelineRoleFactory extends Factory
{
    protected $model = PipelineRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2, false);

        return [
            'user_id' => User::factory(),
            'slug' => str_replace('-', '_', $slug),
            'name' => fake()->words(2, true),
            'emoji' => '🤖',
            'system_prompt' => 'Tu es un agent assistant pour ce projet.',
            'model' => 'claude-sonnet-4-6',
            'is_builtin' => false,
            'prompt_customized' => false,
            'sort_order' => 0,
        ];
    }

    public function builtin(string $slug = 'pm'): static
    {
        $labels = config('maestro.role_labels.'.$slug, ['emoji' => '🤖', 'name' => $slug]);

        return $this->state(fn () => [
            'slug' => $slug,
            'name' => $labels['name'],
            'emoji' => $labels['emoji'],
            'model' => config('maestro.default_models.'.$slug, 'claude-sonnet-4-6'),
            'is_builtin' => true,
        ]);
    }
}
