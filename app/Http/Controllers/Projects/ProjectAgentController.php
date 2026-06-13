<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Services\AgentTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectAgentController extends Controller
{
    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $models = array_keys(config('maestro.model_prices', []));
        $projectSlugs = $project->agents()->pluck('agent_type')->all();

        $validated = $request->validate([
            'agents' => ['required', 'array'],
            'agents.*.agent_type' => ['required', 'string', Rule::in($projectSlugs)],
            'agents.*.is_active' => ['boolean'],
            'agents.*.model' => ['required', 'string', Rule::in($models)],
            'agents.*.system_prompt' => ['required', 'string', 'max:50000'],
            'agents.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['agents'] as $agentData) {
            ProjectAgent::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'agent_type' => $agentData['agent_type'],
                ],
                [
                    'is_active' => $agentData['is_active'] ?? true,
                    'model' => $agentData['model'],
                    'system_prompt' => $agentData['system_prompt'],
                    'sort_order' => $agentData['sort_order'],
                ],
            );
        }

        $modelConfig = collect($validated['agents'])
            ->pluck('model', 'agent_type')
            ->all();

        $project->update(['model_config' => array_merge($project->model_config ?? [], $modelConfig)]);

        return back()->with('success', 'Agents mis à jour.');
    }

    public function test(Request $request, Project $project, string $type, AgentTestService $testService): JsonResponse
    {
        $this->authorize('update', $project);

        $projectAgent = $project->agents()->where('agent_type', $type)->firstOrFail();

        try {
            $result = $testService->test(
                $request->user(),
                $projectAgent->system_prompt,
                $projectAgent->model,
                $project,
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
