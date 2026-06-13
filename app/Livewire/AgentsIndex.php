<?php

namespace App\Livewire;

use App\Models\UserAgent;
use App\Services\AgentTestService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AgentsIndex extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $agents = [];

    public bool $showCreateModal = false;

    public bool $showDeleteConfirm = false;

    public ?int $deletingAgentId = null;

    public string $newSlug = '';

    public string $newName = '';

    public string $newEmoji = '🤖';

    public string $newModel = 'claude-sonnet-4-6';

    public string $newSystemPrompt = '';

    public ?int $expandedAgentId = null;

    public ?string $testOutput = null;

    public ?float $testCost = null;

    public ?string $testError = null;

    public function mount(): void
    {
        $this->authorize('viewAny', UserAgent::class);
        $this->loadAgents();
    }

    public function loadAgents(): void
    {
        $this->agents = UserAgent::query()
            ->where('user_id', Auth::id())
            ->orderBy('sort_order')
            ->get()
            ->map(fn (UserAgent $a) => [
                'id' => $a->id,
                'slug' => $a->slug,
                'name' => $a->name,
                'emoji' => $a->emoji,
                'model' => $a->model,
                'system_prompt' => $a->system_prompt,
                'is_builtin' => $a->is_builtin,
                'sort_order' => $a->sort_order,
            ])
            ->values()
            ->all();
    }

    public function openCreateModal(): void
    {
        $this->reset(['newSlug', 'newName', 'newEmoji', 'newModel', 'newSystemPrompt', 'testOutput', 'testCost', 'testError']);
        $this->newEmoji = '🤖';
        $this->newModel = 'claude-sonnet-4-6';
        $this->newSystemPrompt = 'Tu es un agent assistant spécialisé pour ce projet.';
        $this->showCreateModal = true;
    }

    public function createAgent(): void
    {
        $this->authorize('create', UserAgent::class);

        $models = array_keys(config('maestro.model_prices', []));

        $this->validate([
            'newSlug' => [
                'required',
                'string',
                'regex:/^[a-z][a-z0-9_]*$/',
                'max:50',
                Rule::unique('user_agents', 'slug')->where('user_id', Auth::id()),
                Rule::notIn(array_keys(config('maestro.builtin_agents', []))),
            ],
            'newName' => ['required', 'string', 'max:100'],
            'newEmoji' => ['required', 'string', 'max:8'],
            'newModel' => ['required', 'string', Rule::in($models)],
            'newSystemPrompt' => ['required', 'string', 'max:50000'],
        ], [
            'newSlug.regex' => 'Le slug doit commencer par une lettre et ne contenir que des minuscules, chiffres et underscores.',
            'newSlug.not_in' => 'Ce slug est réservé aux agents système.',
            'newSlug.unique' => 'Ce slug existe déjà dans votre bibliothèque.',
        ]);

        $maxSort = UserAgent::query()->where('user_id', Auth::id())->max('sort_order') ?? -1;

        UserAgent::create([
            'user_id' => Auth::id(),
            'slug' => $this->newSlug,
            'name' => $this->newName,
            'emoji' => $this->newEmoji,
            'model' => $this->newModel,
            'system_prompt' => $this->newSystemPrompt,
            'is_builtin' => false,
            'sort_order' => $maxSort + 1,
        ]);

        $this->showCreateModal = false;
        $this->loadAgents();
        session()->flash('success', 'Agent créé avec succès.');
    }

    public function saveAgent(int $index): void
    {
        $models = array_keys(config('maestro.model_prices', []));
        $agentData = $this->agents[$index] ?? null;

        if (! $agentData) {
            return;
        }

        $userAgent = UserAgent::query()
            ->where('user_id', Auth::id())
            ->findOrFail($agentData['id']);

        $this->authorize('update', $userAgent);

        $this->validate([
            "agents.{$index}.name" => ['required', 'string', 'max:100'],
            "agents.{$index}.emoji" => ['required', 'string', 'max:8'],
            "agents.{$index}.model" => ['required', 'string', Rule::in($models)],
            "agents.{$index}.system_prompt" => ['required', 'string', 'max:50000'],
        ]);

        $originalPrompt = $userAgent->system_prompt;

        $userAgent->update([
            'name' => $agentData['name'],
            'emoji' => $agentData['emoji'],
            'model' => $agentData['model'],
            'system_prompt' => $agentData['system_prompt'],
            'prompt_customized' => $userAgent->prompt_customized || $agentData['system_prompt'] !== $originalPrompt,
        ]);

        session()->flash('success', 'Agent mis à jour.');
        $this->loadAgents();
    }

    public function confirmDelete(int $agentId): void
    {
        $userAgent = UserAgent::query()
            ->where('user_id', Auth::id())
            ->findOrFail($agentId);

        $this->authorize('delete', $userAgent);

        $this->deletingAgentId = $agentId;
        $this->showDeleteConfirm = true;
    }

    public function deleteAgent(): void
    {
        if (! $this->deletingAgentId) {
            return;
        }

        $userAgent = UserAgent::query()
            ->where('user_id', Auth::id())
            ->findOrFail($this->deletingAgentId);

        $this->authorize('delete', $userAgent);

        $userAgent->delete();

        $this->showDeleteConfirm = false;
        $this->deletingAgentId = null;
        $this->loadAgents();
        session()->flash('success', 'Agent supprimé.');
    }

    public function testAgent(int $index, AgentTestService $testService): void
    {
        $agentData = $this->agents[$index] ?? null;

        if (! $agentData) {
            return;
        }

        $userAgent = UserAgent::query()
            ->where('user_id', Auth::id())
            ->findOrFail($agentData['id']);

        $this->authorize('update', $userAgent);

        $this->reset(['testOutput', 'testCost', 'testError']);

        try {
            $result = $testService->test(
                Auth::user(),
                $agentData['system_prompt'],
                $agentData['model'],
            );

            $this->testOutput = $result['output'];
            $this->testCost = $result['cost'];
        } catch (\Throwable $e) {
            $this->testError = $e->getMessage();
        }
    }

    public function toggleAgent(int $index): void
    {
        $this->expandedAgentId = $this->expandedAgentId === $index ? null : $index;
        $this->reset(['testOutput', 'testCost', 'testError']);
    }

    public function render()
    {
        return view('livewire.agents-index', [
            'modelOptions' => array_keys(config('maestro.model_prices', [])),
        ]);
    }
}
