<?php

namespace App\Livewire;

use App\Enums\AgentType;
use App\Enums\ProjectStatus;
use App\Enums\TaskMode;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\ProjectWizardDraft;
use App\Models\UserAgent;
use App\Services\CostEstimatorService;
use App\Services\GitHubContextReader;
use Database\Seeders\UserAgentSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProjectWizard extends Component
{
    public int $step = 1;

    public string $name = '';

    public string $description = '';

    public string $github_repo = '';

    public string $github_branch = 'main';

    public string $github_token = '';

    public bool $read_context_from_repo = false;

    public string $stack = '';

    public string $vision = '';

    public string $conventions = '';

    public string $modules = '';

    public string $design_system = '';

    public string $constraints = '';

    /** @var array<string, array<int, string>> */
    public array $pipeline = [];

    /** @var array<string, array<string, bool>> */
    public array $gates = [];

    /** @var array<string, string> */
    public array $modes = [];

    /** @var array<string, string> */
    public array $models = [];

    /** @var array<string, array<string, mixed>> */
    public array $agents = [];

    public string $selectedTaskType = 'feature';

    /** @var array<string, mixed>|null */
    public ?array $costEstimate = null;

    public function mount(): void
    {
        $draft = ProjectWizardDraft::findOrCreateForUser(Auth::id());
        $this->step = max(1, min(4, (int) $draft->step));
        $data = $draft->data ?? [];

        if (isset($data['step1'])) {
            $this->name = $data['step1']['name'] ?? '';
            $this->description = $data['step1']['description'] ?? '';
            $this->github_repo = $data['step1']['github_repo'] ?? '';
            $this->github_branch = $data['step1']['github_branch'] ?? 'main';
            $this->github_token = $data['step1']['github_token'] ?? session('github_oauth_token', '');
        }

        if (isset($data['step2'])) {
            $this->vision = $data['step2']['vision'] ?? '';
            $this->stack = $data['step2']['stack'] ?? '';
            $this->conventions = $data['step2']['conventions'] ?? '';
            $this->modules = $data['step2']['modules'] ?? '';
            $this->design_system = $data['step2']['design_system'] ?? '';
            $this->constraints = $data['step2']['constraints'] ?? '';
        } elseif (isset($data['prefilled_context'])) {
            $ctx = $data['prefilled_context'];
            $this->vision = $ctx['vision'] ?? '';
            $this->stack = $ctx['stack'] ?? '';
            $this->conventions = $ctx['conventions'] ?? '';
            $this->modules = $ctx['modules'] ?? '';
            $this->design_system = $ctx['design_system'] ?? '';
            $this->constraints = $ctx['constraints'] ?? '';
        }

        if (isset($data['step3'])) {
            $this->pipeline = $data['step3']['pipeline'] ?? [];
            $this->gates = $data['step3']['gates'] ?? [];
            $this->modes = $data['step3']['modes'] ?? [];
        } else {
            $this->pipeline = config('maestro.default_pipelines', []);
            $this->gates = config('maestro.default_gate_config', []);
            $this->modes = collect(config('maestro.default_modes', []))
                ->map(fn ($m) => is_string($m) ? $m : $m)
                ->all();
        }

        if (isset($data['step4'])) {
            $this->models = $data['step4']['models'] ?? [];
            $this->agents = $data['step4']['agents'] ?? [];
        } else {
            $this->initDefaultAgents();
        }

        $this->refreshCostEstimate();
    }

    public function render()
    {
        return view('livewire.project-wizard', [
            'agentTypes' => AgentType::cases(),
            'taskTypes' => TaskType::cases(),
            'taskModes' => TaskMode::cases(),
            'modelOptions' => array_keys(config('maestro.model_prices', [])),
            'progress' => ($this->step / 4) * 100,
        ])->layout('layouts.wizard', [
            'step' => $this->step,
            'progress' => ($this->step / 4) * 100,
            'title' => 'Nouveau projet — Maestro',
        ]);
    }

    public function saveStep1(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'github_repo' => ['required', 'string', 'regex:/^[\w.\-]+\/[\w.\-]+$/'],
            'github_branch' => ['required', 'string', 'max:255'],
            'github_token' => ['nullable', 'string', 'min:10'],
        ], [
            'name.required' => 'Le nom du projet est obligatoire.',
            'github_repo.required' => 'Le dépôt GitHub est obligatoire (format owner/repo).',
            'github_repo.regex' => 'Format invalide — utilisez owner/repo (ex. mon-org/mon-projet).',
            'github_branch.required' => 'La branche par défaut est obligatoire.',
            'github_token.min' => 'Le token GitHub doit contenir au moins 10 caractères, ou utilisez OAuth.',
        ]);

        $stepData = [
            'name' => $this->name,
            'description' => $this->description,
            'github_repo' => $this->github_repo,
            'github_branch' => $this->github_branch,
            'github_token' => $this->github_token ?: session('github_oauth_token'),
        ];

        if ($this->read_context_from_repo && $stepData['github_token']) {
            $prefilled = app(GitHubContextReader::class)->read(
                $this->github_repo,
                $stepData['github_token'],
            );
            $this->stack = $prefilled['stack'] ?? $this->stack;
            $this->conventions = $prefilled['conventions'] ?? $this->conventions;
            $this->modules = $prefilled['modules'] ?? $this->modules;
            $this->design_system = $prefilled['design_system'] ?? $this->design_system;
            $this->constraints = $prefilled['constraints'] ?? $this->constraints;
            $this->persistDraft(2, ['step1' => $stepData, 'prefilled_context' => $prefilled]);
        } else {
            $this->persistDraft(2, ['step1' => $stepData]);
        }

        $this->step = 2;
    }

    public function saveStep2(): void
    {
        $this->validate([
            'vision' => ['nullable', 'string', 'max:10000'],
            'stack' => ['required', 'string', 'max:10000'],
            'conventions' => ['required', 'string', 'max:10000'],
            'modules' => ['required', 'string', 'max:10000'],
            'design_system' => ['required', 'string', 'max:10000'],
            'constraints' => ['required', 'string', 'max:10000'],
        ], [
            'stack.required' => 'La stack technique est obligatoire.',
            'conventions.required' => 'Les conventions de code sont obligatoires.',
            'modules.required' => 'Les modules / architecture sont obligatoires.',
            'design_system.required' => 'Le design system est obligatoire.',
            'constraints.required' => 'Les contraintes sont obligatoires.',
        ]);

        $this->persistDraft(3, ['step2' => [
            'vision' => $this->vision,
            'stack' => $this->stack,
            'conventions' => $this->conventions,
            'modules' => $this->modules,
            'design_system' => $this->design_system,
            'constraints' => $this->constraints,
        ]]);

        $this->step = 3;
    }

    public function saveStep3(): void
    {
        $this->persistDraft(4, ['step3' => [
            'pipeline' => $this->pipeline,
            'gates' => $this->gates,
            'modes' => $this->modes,
        ]]);

        $this->step = 4;
        $this->refreshCostEstimate();
    }

    public function saveStep4(): void
    {
        $this->persistDraft(4, ['step4' => [
            'models' => $this->models,
            'agents' => $this->agents,
        ]]);
    }

    public function finalize()
    {
        $this->saveStep4();

        $draft = ProjectWizardDraft::where('user_id', Auth::id())->firstOrFail();
        $data = $draft->data;

        $project = Project::create([
            'user_id' => Auth::id(),
            'name' => $data['step1']['name'],
            'description' => $data['step1']['description'] ?? null,
            'github_repo' => $data['step1']['github_repo'],
            'github_branch' => $data['step1']['github_branch'],
            'github_token' => $data['step1']['github_token'] ?? null,
            'context' => $data['step2'],
            'pipeline_config' => $data['step3']['pipeline'],
            'gate_config' => $data['step3']['gates'],
            'default_modes' => $data['step3']['modes'],
            'model_config' => $data['step4']['models'],
            'status' => ProjectStatus::Active,
        ]);

        foreach ($data['step4']['agents'] as $type => $config) {
            $userAgent = UserAgent::query()
                ->where('user_id', Auth::id())
                ->where('slug', $type)
                ->first();

            ProjectAgent::create([
                'project_id' => $project->id,
                'user_agent_id' => $userAgent?->id,
                'agent_type' => $type,
                'is_active' => $config['is_active'] ?? true,
                'model' => $config['model'],
                'system_prompt' => $config['system_prompt'],
                'sort_order' => $config['sort_order'],
            ]);
        }

        $draft->delete();
        session()->forget('github_oauth_token');

        return redirect()->route('projects.show', $project)->with('success', 'Projet créé !');
    }

    public function goToStep(int $step): void
    {
        $this->step = max(1, min(4, $step));
    }

    public function updatedPipeline(): void
    {
        $this->refreshCostEstimate();
    }

    public function updatedSelectedTaskType(): void
    {
        $this->refreshCostEstimate();
    }

    public function updatePipelineOrder(string $taskType, array $order): void
    {
        $this->pipeline[$taskType] = $order;
        $this->refreshCostEstimate();
    }

    private function refreshCostEstimate(): void
    {
        $draftProject = new Project([
            'pipeline_config' => $this->pipeline,
            'model_config' => $this->models,
            'default_modes' => $this->modes,
        ]);

        $draftTask = new \App\Models\Task([
            'type' => TaskType::from($this->selectedTaskType),
            'mode' => TaskMode::from($this->modes[$this->selectedTaskType] ?? 'manual'),
            'project_id' => 0,
        ]);
        $draftTask->setRelation('project', $draftProject);

        $this->costEstimate = app(CostEstimatorService::class)->estimate($draftTask);
    }

    private function initDefaultAgents(): void
    {
        $user = Auth::user();

        if ($user->agents()->count() === 0) {
            UserAgentSeeder::seedForUser($user);
        }

        foreach ($user->agents()->orderBy('sort_order')->get() as $userAgent) {
            $slug = $userAgent->slug;
            $this->models[$slug] = $userAgent->model;
            $this->agents[$slug] = [
                'is_active' => true,
                'model' => $userAgent->model,
                'system_prompt' => $userAgent->system_prompt,
                'sort_order' => $userAgent->sort_order,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $stepData
     */
    private function persistDraft(int $step, array $stepData): void
    {
        $draft = ProjectWizardDraft::findOrCreateForUser(Auth::id());
        $data = array_merge($draft->data ?? [], $stepData);
        $draft->update(['step' => $step, 'data' => $data]);
    }
}
