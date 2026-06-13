<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Models\UserAgent;

class AgentTestService
{
    public function __construct(
        private readonly AnthropicClient $anthropic,
        private readonly AgentRunnerService $runner,
    ) {}

    /**
     * @return array{output: string, cost: float}
     */
    public function test(
        User $user,
        string $systemPrompt,
        string $model,
        ?Project $project = null,
    ): array {
        if (! $user->claude_api_key) {
            throw new \RuntimeException('Clé API Claude non configurée.');
        }

        $systemBlocks = [];

        if ($project) {
            $systemBlocks[] = [
                'type' => 'text',
                'text' => $this->runner->buildProjectContext($project),
            ];
        }

        $systemBlocks[] = [
            'type' => 'text',
            'text' => $systemPrompt,
        ];

        $response = $this->anthropic->createMessage(
            apiKey: $user->claude_api_key,
            model: $model,
            systemBlocks: $systemBlocks,
            userMessage: 'Réponds brièvement pour confirmer que tu es opérationnel et que tu comprends ton rôle.',
            maxTokens: 256,
        );

        $usage = $response['usage'];
        $cost = $this->runner->calculateCost(
            $model,
            $usage['input_tokens'],
            $usage['output_tokens'],
            $usage['cache_read_input_tokens'],
        );

        return [
            'output' => $response['text'],
            'cost' => $cost,
        ];
    }

    public function testUserAgent(User $user, UserAgent $agent, ?Project $project = null): array
    {
        return $this->test($user, $agent->system_prompt, $agent->model, $project);
    }
}
