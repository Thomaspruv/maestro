<?php

namespace App\Http\Controllers\Projects;

use App\Agents\AgentFactory;
use App\Enums\AgentType;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Services\AgentRunnerService;
use App\Services\AnthropicClient;
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
        $agentTypes = array_column(AgentType::cases(), 'value');

        $validated = $request->validate([
            'agents' => ['required', 'array'],
            'agents.*.agent_type' => ['required', 'string', Rule::in($agentTypes)],
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

        return back()->with('success', 'Agents mis à jour.');
    }

    public function test(Request $request, Project $project, string $type, AnthropicClient $anthropic, AgentRunnerService $runner): JsonResponse
    {
        $this->authorize('update', $project);

        abort_unless(in_array($type, array_column(AgentType::cases(), 'value'), true), 404);

        $projectAgent = $project->agents()->where('agent_type', $type)->firstOrFail();
        $apiKey = $request->user()->claude_api_key;

        if (! $apiKey) {
            return response()->json(['error' => 'Clé API Claude non configurée.'], 422);
        }

        $agent = AgentFactory::make(AgentType::from($type), $project);
        $model = $projectAgent->model ?? config("maestro.default_models.{$type}", 'claude-sonnet-4-6');

        $response = $anthropic->createMessage(
            apiKey: $apiKey,
            model: $model,
            systemBlocks: [
                ['type' => 'text', 'text' => $runner->buildProjectContext($project)],
                ['type' => 'text', 'text' => $agent->systemPrompt()],
            ],
            userMessage: 'Réponds brièvement pour confirmer que tu es opérationnel sur ce projet.',
            maxTokens: 256,
        );

        $usage = $response['usage'];
        $cost = $runner->calculateCost($model, $usage['input_tokens'], $usage['output_tokens'], $usage['cache_read_input_tokens']);

        return response()->json([
            'output' => $response['text'],
            'cost' => $cost,
        ]);
    }
}
