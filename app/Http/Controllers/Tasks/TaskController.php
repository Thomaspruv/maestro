<?php

namespace App\Http\Controllers\Tasks;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\CostEstimatorService;
use App\Services\OrchestratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function create(Project $project): View
    {
        $this->authorize('view', $project);

        $defaultMode = $project->default_modes[request('type', 'feature')] ?? 'manual';

        return view('tasks.create', compact('project', 'defaultMode'));
    }

    public function store(StoreTaskRequest $request, Project $project, CostEstimatorService $estimator): RedirectResponse
    {
        $this->authorize('view', $project);

        $task = $project->tasks()->create($request->validated());

        $estimate = $estimator->estimate($task);
        $task->update(['estimated_cost' => $estimate['total_mid']]);

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('success', 'Tâche créée.');
    }

    public function show(Project $project, Task $task): View
    {
        $this->authorize('view', $task);
        abort_unless($task->project_id === $project->id, 404);

        $task->load(['pipelineSteps', 'gates.pipelineStep']);

        return view('tasks.show', compact('project', 'task'));
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        abort_unless($task->project_id === $project->id, 404);

        $task->update($request->validated());

        return back()->with('success', 'Tâche mise à jour.');
    }

    public function start(Project $project, Task $task, OrchestratorService $orchestrator): RedirectResponse
    {
        $this->authorize('update', $task);
        abort_unless($task->project_id === $project->id, 404);

        if (OrchestratorService::internalPipelineEnabled()) {
            $task->update([
                'status' => TaskStatus::InProgress,
                'current_role' => null,
            ]);
            $orchestrator->advance($task->fresh());

            return back()->with('success', 'Pipeline démarré.');
        }

        $orchestrator->handoffToHermes($task);

        return back()->with('success', 'Tâche envoyée à Hermes.');
    }

    public function retry(Project $project, Task $task, OrchestratorService $orchestrator): RedirectResponse
    {
        $this->authorize('update', $task);
        abort_unless($task->project_id === $project->id, 404);

        if (OrchestratorService::internalPipelineEnabled()) {
            $task->update([
                'status' => TaskStatus::InProgress,
                'current_role' => null,
            ]);
            $orchestrator->advance($task->fresh());

            return back()->with('success', 'Pipeline relancé.');
        }

        $orchestrator->handoffToHermes($task);

        return back()->with('success', 'Tâche renvoyée à Hermes.');
    }

    public function abandon(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        abort_unless($task->project_id === $project->id, 404);

        $task->update([
            'status' => TaskStatus::Failed,
            'current_role' => null,
        ]);

        return back()->with('success', 'Tâche abandonnée.');
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);
        abort_unless($task->project_id === $project->id, 404);

        $task->delete();

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Tâche supprimée.');
    }

    public function cockpit(Project $project, Task $task): View
    {
        $this->authorize('update', $task);
        abort_unless($task->project_id === $project->id, 404);

        return view('tasks.cockpit', compact('project', 'task'));
    }
}
