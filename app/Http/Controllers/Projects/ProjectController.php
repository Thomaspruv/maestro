<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $projects = Project::query()
            ->forUser($request->user())
            ->where('status', ProjectStatus::Active)
            ->withCount('tasks')
            ->latest()
            ->get();

        return view('projects.index', compact('projects'));
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $tasks = $project->tasks()
            ->with(['agentRuns', 'gates'])
            ->orderBy('sort_order')
            ->get()
            ->groupBy('status');

        return view('projects.show', compact('project', 'tasks'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return back()->with('success', 'Projet mis à jour.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->update(['status' => ProjectStatus::Archived]);

        return redirect()
            ->route('projects.index')
            ->with('success', 'Projet archivé.');
    }
}
