<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\View\View;

class PipelineCockpitController extends Controller
{
    public function show(Project $project, Task $task): View
    {
        $this->authorize('update', $task);
        abort_unless($task->project_id === $project->id, 404);

        $task->load(['agentRuns', 'gates.agentRun']);

        return view('tasks.cockpit', compact('project', 'task'));
    }
}
