<?php

namespace App\Services\Mcp;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

trait ResolvesMcpResources
{
    protected function findUserProject(User $user, int $projectId): Project
    {
        $project = Project::query()
            ->where('user_id', $user->id)
            ->whereKey($projectId)
            ->first();

        if ($project === null) {
            throw McpToolException::notFound('project');
        }

        return $project;
    }

    protected function findUserTask(User $user, int $taskId): Task
    {
        $task = Task::query()
            ->whereKey($taskId)
            ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
            ->first();

        if ($task === null) {
            throw McpToolException::notFound('task');
        }

        return $task;
    }
}
