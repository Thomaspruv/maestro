<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\User;
use App\Models\UserAgent;

class ProjectAgentSyncService
{
    /**
     * Copie les agents du compte utilisateur vers un projet nouvellement créé.
     *
     * @return array<string, string> model_config keyed by slug
     */
    public function copyUserAgentsToProject(User $user, Project $project): array
    {
        $modelConfig = [];

        $userAgents = UserAgent::query()
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->get();

        foreach ($userAgents as $userAgent) {
            ProjectAgent::create([
                'project_id' => $project->id,
                'user_agent_id' => $userAgent->id,
                'agent_type' => $userAgent->slug,
                'is_active' => true,
                'model' => $userAgent->model,
                'system_prompt' => $userAgent->system_prompt,
                'sort_order' => $userAgent->sort_order,
            ]);

            $modelConfig[$userAgent->slug] = $userAgent->model;
        }

        return $modelConfig;
    }

    /**
     * @return array<string, array{emoji: string, name: string}>
     */
    public function resolveLabelsForUser(User $user): array
    {
        $labels = [];

        foreach (config('maestro.agent_labels', []) as $slug => $config) {
            $labels[$slug] = [
                'emoji' => $config['emoji'] ?? '🤖',
                'name' => $config['name'] ?? $slug,
            ];
        }

        UserAgent::query()
            ->where('user_id', $user->id)
            ->get()
            ->each(function (UserAgent $agent) use (&$labels): void {
                $labels[$agent->slug] = $agent->label();
            });

        return $labels;
    }

    public function resolveLabel(User $user, string $slug): array
    {
        $userAgent = UserAgent::query()
            ->where('user_id', $user->id)
            ->where('slug', $slug)
            ->first();

        if ($userAgent) {
            return $userAgent->label();
        }

        $config = config('maestro.agent_labels.'.$slug);

        if (is_array($config)) {
            return [
                'emoji' => $config['emoji'] ?? '🤖',
                'name' => $config['name'] ?? $slug,
            ];
        }

        return ['emoji' => '🤖', 'name' => $slug];
    }
}
