<?php

namespace App\Services;

use App\Models\PipelineRole;
use App\Models\Project;
use App\Models\ProjectRole;
use App\Models\User;

class ProjectRoleSyncService
{
    /**
     * Copie les rôles du compte utilisateur vers un projet nouvellement créé.
     *
     * @return array<string, string> model_config keyed by slug
     */
    public function copyUserRolesToProject(User $user, Project $project): array
    {
        $modelConfig = [];

        $pipelineRoles = PipelineRole::query()
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->get();

        foreach ($pipelineRoles as $pipelineRole) {
            ProjectRole::create([
                'project_id' => $project->id,
                'pipeline_role_id' => $pipelineRole->id,
                'role' => $pipelineRole->slug,
                'is_active' => true,
                'model' => $pipelineRole->model,
                'system_prompt' => $pipelineRole->system_prompt,
                'sort_order' => $pipelineRole->sort_order,
            ]);

            $modelConfig[$pipelineRole->slug] = $pipelineRole->model;
        }

        return $modelConfig;
    }

    /**
     * @return array<string, array{emoji: string, name: string}>
     */
    public function resolveLabelsForUser(User $user): array
    {
        $labels = [];

        foreach (config('maestro.role_labels', []) as $slug => $config) {
            $labels[$slug] = [
                'emoji' => $config['emoji'] ?? '🤖',
                'name' => $config['name'] ?? $slug,
            ];
        }

        PipelineRole::query()
            ->where('user_id', $user->id)
            ->get()
            ->each(function (PipelineRole $role) use (&$labels): void {
                $labels[$role->slug] = $role->label();
            });

        return $labels;
    }

    public function resolveLabel(User $user, string $slug): array
    {
        $pipelineRole = PipelineRole::query()
            ->where('user_id', $user->id)
            ->where('slug', $slug)
            ->first();

        if ($pipelineRole) {
            return $pipelineRole->label();
        }

        $config = config('maestro.role_labels.'.$slug);

        if (is_array($config)) {
            return [
                'emoji' => $config['emoji'] ?? '🤖',
                'name' => $config['name'] ?? $slug,
            ];
        }

        return ['emoji' => '🤖', 'name' => $slug];
    }
}
