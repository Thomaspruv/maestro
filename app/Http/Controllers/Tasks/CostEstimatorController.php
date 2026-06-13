<?php

namespace App\Http\Controllers\Tasks;

use App\Enums\TaskMode;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Services\CostEstimatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostEstimatorController extends Controller
{
    public function estimateDraft(Request $request, Project $project, CostEstimatorService $estimator): JsonResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => ['required', Rule::enum(TaskType::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'mode' => ['sometimes', Rule::enum(TaskMode::class)],
        ]);

        $draft = new Task(array_merge($validated, ['project_id' => $project->id]));
        $draft->setRelation('project', $project);

        return response()->json($estimator->estimate($draft));
    }

    public function estimate(Project $project, Task $task, CostEstimatorService $estimator): JsonResponse
    {
        $this->authorize('view', $task);
        abort_unless($task->project_id === $project->id, 404);

        return response()->json($estimator->estimate($task));
    }
}
