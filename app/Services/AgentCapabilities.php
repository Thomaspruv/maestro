<?php

namespace App\Services;

use App\Models\AgentRun;
use App\Models\Project;
use App\Models\UserAgent;
use Database\Seeders\AgentPromptSeeder;

class AgentCapabilities
{
    public static function runner(string $slug): string
    {
        return config("maestro.builtin_agents.{$slug}.runner", 'api');
    }

    public static function queue(string $slug): string
    {
        return config("maestro.builtin_agents.{$slug}.queue", 'agents');
    }

    public static function postAction(string $slug): ?string
    {
        return config("maestro.builtin_agents.{$slug}.post_action");
    }

    public static function isBuiltin(string $slug): bool
    {
        return array_key_exists($slug, config('maestro.builtin_agents', []));
    }

    public static function resolveSystemPrompt(string $slug, Project $project): string
    {
        $projectAgent = $project->agents()->where('agent_type', $slug)->first();

        if ($projectAgent?->system_prompt) {
            return $projectAgent->system_prompt;
        }

        $userAgent = UserAgent::query()
            ->where('user_id', $project->user_id)
            ->where('slug', $slug)
            ->first();

        if ($userAgent?->system_prompt) {
            return $userAgent->system_prompt;
        }

        if (self::isBuiltin($slug)) {
            return AgentPromptSeeder::for($slug);
        }

        return 'Tu es un agent assistant pour ce projet.';
    }

    /**
     * Résout le modèle Claude pour un agent (ProjectAgent → model_config → UserAgent → défaut).
     * Si un AgentRun est fourni avec un modèle déjà figé, celui-ci prime.
     */
    public static function resolveModel(string $slug, Project $project, ?AgentRun $run = null): string
    {
        if ($run !== null && filled($run->model)) {
            return $run->model;
        }

        $project->loadMissing('agents');

        $projectAgent = $project->agents->first(fn ($agent) => $agent->agent_type === $slug);

        if (filled($projectAgent?->model)) {
            return $projectAgent->model;
        }

        $modelConfig = $project->model_config ?? [];

        if (isset($modelConfig[$slug]) && filled($modelConfig[$slug])) {
            return $modelConfig[$slug];
        }

        $userAgent = UserAgent::query()
            ->where('user_id', $project->user_id)
            ->where('slug', $slug)
            ->first();

        if (filled($userAgent?->model)) {
            return $userAgent->model;
        }

        return config("maestro.default_models.{$slug}", 'claude-sonnet-4-6');
    }
}
