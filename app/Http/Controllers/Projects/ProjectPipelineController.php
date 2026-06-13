<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreWizardStep3Request;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class ProjectPipelineController extends Controller
{
    public function update(StoreWizardStep3Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validated();

        $project->update([
            'pipeline_config' => $data['pipeline'],
            'gate_config' => $data['gates'],
            'default_modes' => $data['modes'],
        ]);

        return back()->with('success', 'Pipeline et gates mis à jour.');
    }
}
