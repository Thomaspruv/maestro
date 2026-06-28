<?php

namespace Database\Seeders;

use App\Enums\PipelineRoleSlug;
use App\Models\User;
use App\Models\PipelineRole;

class PipelineRoleSeeder
{
    /** @var array<int, string> */
    private const EXTRA_BUILTIN_SLUGS = ['discovery'];

    public static function seedForUser(User $user): void
    {
        $sortOrder = 0;

        foreach (PipelineRoleSlug::cases() as $agentType) {
            self::seedBuiltinAgent($user, $agentType->value, $sortOrder++);
        }

        foreach (self::EXTRA_BUILTIN_SLUGS as $slug) {
            self::seedBuiltinAgent($user, $slug, $sortOrder++);
        }
    }

    private static function seedBuiltinAgent(User $user, string $slug, int $sortOrder): void
    {
        $labels = config('maestro.role_labels.'.$slug, ['emoji' => '🤖', 'name' => $slug]);

        PipelineRole::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'slug' => $slug,
            ],
            [
                'name' => $labels['name'],
                'emoji' => $labels['emoji'],
                'system_prompt' => AgentPromptSeeder::for($slug),
                'model' => config('maestro.default_models.'.$slug, 'claude-sonnet-4-6'),
                'is_builtin' => true,
                'sort_order' => $sortOrder,
            ],
        );
    }

    /**
     * Met à jour les prompts built-in non personnalisés avec les derniers defaults.
     */
    public static function refreshBuiltinPrompts(User $user): void
    {
        $slugs = array_merge(
            array_map(fn (PipelineRoleSlug $type) => $type->value, PipelineRoleSlug::cases()),
            self::EXTRA_BUILTIN_SLUGS,
        );

        foreach ($slugs as $slug) {
            PipelineRole::query()
                ->where('user_id', $user->id)
                ->where('slug', $slug)
                ->where('is_builtin', true)
                ->where('prompt_customized', false)
                ->update([
                    'system_prompt' => AgentPromptSeeder::for($slug),
                ]);
        }
    }

    /**
     * Met à jour les modèles built-in depuis config/maestro.php (ex. Dev → Haiku).
     */
    public static function refreshBuiltinModels(User $user): void
    {
        $slugs = array_merge(
            array_map(fn (PipelineRoleSlug $type) => $type->value, PipelineRoleSlug::cases()),
            self::EXTRA_BUILTIN_SLUGS,
        );

        foreach ($slugs as $slug) {
            PipelineRole::query()
                ->where('user_id', $user->id)
                ->where('slug', $slug)
                ->where('is_builtin', true)
                ->update([
                    'model' => config('maestro.default_models.'.$slug, 'claude-sonnet-4-6'),
                ]);
        }
    }
}
