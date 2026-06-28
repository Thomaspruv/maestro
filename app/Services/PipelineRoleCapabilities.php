<?php

namespace App\Services;

use App\Models\PipelineRole;
use App\Models\PipelineStep;
use App\Models\Project;
use App\Models\ProjectRole;
use Database\Seeders\AgentPromptSeeder;

class PipelineRoleCapabilities
{
    public static function runner(string $slug): string
    {
        return config("maestro.builtin_roles.{$slug}.runner", 'api');
    }

    public static function queue(string $slug): string
    {
        return config("maestro.builtin_roles.{$slug}.queue", 'roles');
    }

    public static function postAction(string $slug): ?string
    {
        return config("maestro.builtin_roles.{$slug}.post_action");
    }

    public static function isBuiltin(string $slug): bool
    {
        return array_key_exists($slug, config('maestro.builtin_roles', []));
    }

    public static function resolveSystemPrompt(string $slug, Project $project): string
    {
        $projectRole = $project->roles()->where('role', $slug)->first();

        if ($projectRole?->system_prompt) {
            return $projectRole->system_prompt;
        }

        $pipelineRole = PipelineRole::query()
            ->where('user_id', $project->user_id)
            ->where('slug', $slug)
            ->first();

        if ($pipelineRole?->system_prompt) {
            return $pipelineRole->system_prompt;
        }

        if (self::isBuiltin($slug)) {
            return AgentPromptSeeder::for($slug);
        }

        return 'Tu es un rôle assistant pour ce projet.';
    }

    /**
     * Résout le modèle Claude pour un rôle (ProjectRole → model_config → PipelineRole → défaut).
     * Si un PipelineStep est fourni avec un modèle déjà figé, celui-ci prime.
     */
    public static function resolveModel(string $slug, Project $project, ?PipelineStep $step = null): string
    {
        if ($step !== null && filled($step->model)) {
            return $step->model;
        }

        $project->loadMissing('roles');

        $projectRole = $project->roles->first(fn ($role) => $role->role === $slug);

        if (filled($projectRole?->model)) {
            return $projectRole->model;
        }

        $modelConfig = $project->model_config ?? [];

        if (isset($modelConfig[$slug]) && filled($modelConfig[$slug])) {
            return $modelConfig[$slug];
        }

        $pipelineRole = PipelineRole::query()
            ->where('user_id', $project->user_id)
            ->where('slug', $slug)
            ->first();

        if (filled($pipelineRole?->model)) {
            return $pipelineRole->model;
        }

        return config("maestro.default_models.{$slug}", 'claude-sonnet-4-6');
    }
}
