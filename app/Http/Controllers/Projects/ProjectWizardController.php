<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreWizardStep1Request;
use App\Http\Requests\Projects\StoreWizardStep2Request;
use App\Http\Requests\Projects\StoreWizardStep3Request;
use App\Http\Requests\Projects\StoreWizardStep4Request;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\ProjectWizardDraft;
use App\Services\GitHubContextReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectWizardController extends Controller
{
    public function create(): View
    {
        $draft = ProjectWizardDraft::findOrCreateForUser(auth()->id());

        return view('projects.wizard', compact('draft'));
    }

    public function storeStep1(StoreWizardStep1Request $request): JsonResponse
    {
        $draft = ProjectWizardDraft::updateOrCreate(
            ['user_id' => auth()->id()],
            ['step' => 2, 'data' => array_merge(
                ProjectWizardDraft::where('user_id', auth()->id())->value('data') ?? [],
                ['step1' => $request->validated()],
            )],
        );

        $prefilledContext = null;

        if ($request->boolean('read_context_from_repo') && auth()->user()->github_token) {
            $prefilledContext = app(GitHubContextReader::class)->read(
                $request->validated('github_repo'),
                auth()->user()->github_token,
                $request->validated('github_branch'),
            );

            $data = $draft->data;
            $data['prefilled_context'] = $prefilledContext;
            $draft->update(['data' => $data]);
        }

        return response()->json([
            'step' => 2,
            'prefilled_context' => $prefilledContext,
        ]);
    }

    public function storeStep2(StoreWizardStep2Request $request): JsonResponse
    {
        $draft = $this->updateDraftStep(3, ['step2' => $request->validated()]);

        return response()->json(['step' => 3, 'draft' => $draft->data]);
    }

    public function storeStep3(StoreWizardStep3Request $request): JsonResponse
    {
        $draft = $this->updateDraftStep(4, ['step3' => $request->validated()]);

        return response()->json(['step' => 4, 'draft' => $draft->data]);
    }

    public function storeStep4(StoreWizardStep4Request $request): JsonResponse
    {
        $draft = $this->updateDraftStep(4, ['step4' => $request->validated()]);

        return response()->json(['step' => 4, 'draft' => $draft->data]);
    }

    public function finalize(): RedirectResponse
    {
        $draft = ProjectWizardDraft::where('user_id', auth()->id())->firstOrFail();
        $data = $draft->data;

        $project = Project::create([
            'user_id' => auth()->id(),
            'name' => $data['step1']['name'],
            'description' => $data['step1']['description'] ?? null,
            'github_repo' => $data['step1']['github_repo'],
            'github_branch' => $data['step1']['github_branch'],
            'github_token' => null,
            'context' => $data['step2'],
            'pipeline_config' => $data['step3']['pipeline'],
            'gate_config' => $data['step3']['gates'],
            'default_modes' => $data['step3']['modes'],
            'model_config' => $data['step4']['models'],
        ]);

        foreach ($data['step4']['agents'] as $type => $config) {
            ProjectAgent::create([
                'project_id' => $project->id,
                'agent_type' => $type,
                'is_active' => $config['is_active'] ?? true,
                'model' => $config['model'],
                'system_prompt' => $config['system_prompt'],
                'sort_order' => $config['sort_order'],
            ]);
        }

        $draft->delete();
        session()->forget('github_oauth_token');

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Projet créé !');
    }

    /**
     * @param  array<string, mixed>  $stepData
     */
    private function updateDraftStep(int $step, array $stepData): ProjectWizardDraft
    {
        $draft = ProjectWizardDraft::findOrCreateForUser(auth()->id());
        $data = $draft->data ?? [];
        $data = array_merge($data, $stepData);

        $draft->update(['step' => $step, 'data' => $data]);

        return $draft->fresh();
    }
}
