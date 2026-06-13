<?php

use App\Models\Task;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('task.{taskId}', function ($user, int $taskId) {
    $task = Task::query()->with('project')->find($taskId);

    return $task && $user->id === $task->project->user_id;
});
