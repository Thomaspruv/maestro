<?php

namespace App\Services\Mcp\Tools;

use App\Enums\ProjectStatus;
use App\Models\User;
use App\Services\Mcp\Contracts\McpTool;

class ListProjectsTool implements McpTool
{
    public function name(): string
    {
        return 'list_projects';
    }

    public function description(): string
    {
        return 'Liste les projets actifs de l\'utilisateur.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        $projects = $user->projects()
            ->where('status', ProjectStatus::Active)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'github_repo', 'github_branch', 'status']);

        return [
            'projects' => $projects->map(fn ($project) => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'github_repo' => $project->github_repo,
                'github_branch' => $project->github_branch,
                'status' => $project->status->value,
            ])->values()->all(),
        ];
    }
}
