<?php

namespace App\Livewire;

use App\Enums\TaskMode;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Services\ProjectAgentSyncService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProjectSettings extends Component
{
    public Project $project;

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

    /** @var array<int, array<string, mixed>> */
    public array $agents = [];

    public string $activeSection = 'context';

    public ?int $expandedAgentId = null;

    public function mount(Project $project): void
    {
        $this->authorize('update', $project);
        $this->project = $project->load(['agents.promptHistories']);

        $context = $project->context ?? [];
        $this->vision = $context['vision'] ?? '';
        $this->stack = $context['stack'] ?? '';
        $this->conventions = $context['conventions'] ?? '';
        $this->modules = $context['modules'] ?? '';
        $this->design_system = $context['design_system'] ?? '';
        $this->constraints = $context['constraints'] ?? '';

        $this->pipeline = $project->pipeline_config ?? config('maestro.default_pipelines', []);
        $this->gates = $project->gate_config ?? config('maestro.default_gate_config', []);
        $this->modes = $project->default_modes ?? config('maestro.default_modes', []);

        $this->agents = $project->agents->map(fn (ProjectAgent $a) => [
            'id' => $a->id,
            'agent_type' => $a->agent_type,
            'is_active' => $a->is_active,
            'model' => $a->model,
            'system_prompt' => $a->system_prompt,
            'sort_order' => $a->sort_order,
            'histories' => $a->promptHistories->take(5)->pluck('system_prompt', 'created_at')->all(),
        ])->values()->all();
    }

    public function saveContext(): void
    {
        $this->validate([
            'vision' => ['nullable', 'string', 'max:10000'],
            'stack' => ['required', 'string', 'max:10000'],
            'conventions' => ['required', 'string', 'max:10000'],
            'modules' => ['required', 'string', 'max:10000'],
            'design_system' => ['required', 'string', 'max:10000'],
            'constraints' => ['required', 'string', 'max:10000'],
        ]);

        $this->project->update([
            'context' => [
                'vision' => $this->vision,
                'stack' => $this->stack,
                'conventions' => $this->conventions,
                'modules' => $this->modules,
                'design_system' => $this->design_system,
                'constraints' => $this->constraints,
            ],
        ]);

        session()->flash('success', 'Contexte du projet mis à jour.');
    }

    public function saveAgents(): void
    {
        $models = array_keys(config('maestro.model_prices', []));
        $projectSlugs = $this->project->agents()->pluck('agent_type')->all();

        $this->validate([
            'agents.*.agent_type' => ['required', 'string', Rule::in($projectSlugs)],
            'agents.*.is_active' => ['boolean'],
            'agents.*.model' => ['required', 'string', Rule::in($models)],
            'agents.*.system_prompt' => ['required', 'string', 'max:50000'],
            'agents.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $modelConfig = $this->project->model_config ?? [];

        foreach ($this->agents as $agentData) {
            ProjectAgent::updateOrCreate(
                [
                    'project_id' => $this->project->id,
                    'agent_type' => $agentData['agent_type'],
                ],
                [
                    'is_active' => $agentData['is_active'] ?? true,
                    'model' => $agentData['model'],
                    'system_prompt' => $agentData['system_prompt'],
                    'sort_order' => $agentData['sort_order'],
                ],
            );

            $modelConfig[$agentData['agent_type']] = $agentData['model'];
        }

        $this->project->update(['model_config' => $modelConfig]);

        session()->flash('success', 'Agents mis à jour.');
    }

    public function savePipeline(): void
    {
        $this->project->update([
            'pipeline_config' => $this->pipeline,
            'gate_config' => $this->gates,
            'default_modes' => $this->modes,
        ]);

        session()->flash('success', 'Pipeline et gates mis à jour.');
    }

    public function toggleAgent(int $index): void
    {
        $this->expandedAgentId = $this->expandedAgentId === $index ? null : $index;
    }

    public function render()
    {
        $labelService = app(ProjectAgentSyncService::class);

        return view('livewire.project-settings', [
            'taskTypes' => TaskType::cases(),
            'taskModes' => TaskMode::cases(),
            'modelOptions' => array_keys(config('maestro.model_prices', [])),
            'agentLabels' => $labelService->resolveLabelsForUser($this->project->user),
        ]);
    }
}
