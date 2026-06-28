<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectRole;
use App\Services\PipelineRoleTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectRoleController extends Controller
{
    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $models = array_keys(config('maestro.model_prices', []));
        $projectSlugs = $project->roles()->pluck('role')->all();

        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*.role' => ['required', 'string', Rule::in($projectSlugs)],
            'roles.*.is_active' => ['boolean'],
            'roles.*.model' => ['required', 'string', Rule::in($models)],
            'roles.*.system_prompt' => ['required', 'string', 'max:50000'],
            'roles.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['roles'] as $roleData) {
            ProjectRole::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'role' => $roleData['role'],
                ],
                [
                    'is_active' => $roleData['is_active'] ?? true,
                    'model' => $roleData['model'],
                    'system_prompt' => $roleData['system_prompt'],
                    'sort_order' => $roleData['sort_order'],
                ],
            );
        }

        $modelConfig = collect($validated['roles'])
            ->pluck('model', 'role')
            ->all();

        $project->update(['model_config' => array_merge($project->model_config ?? [], $modelConfig)]);

        return back()->with('success', 'Rôles mis à jour.');
    }

    public function test(Request $request, Project $project, string $type, PipelineRoleTestService $testService): JsonResponse
    {
        $this->authorize('update', $project);

        $projectRole = $project->roles()->where('role', $type)->firstOrFail();

        try {
            $result = $testService->test(
                $request->user(),
                $projectRole->system_prompt,
                $projectRole->model,
                $project,
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
