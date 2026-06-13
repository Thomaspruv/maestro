<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
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

    public static function isDev(string $slug): bool
    {
        return self::runner($slug) === 'dev';
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
}
