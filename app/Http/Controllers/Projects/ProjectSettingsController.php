<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreWizardStep2Request;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectSettingsController extends Controller
{
    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.settings', compact('project'));
    }

    public function update(StoreWizardStep2Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update(['context' => $request->validated()]);

        return back()->with('success', 'Contexte du projet mis à jour.');
    }
}
