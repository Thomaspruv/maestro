<?php

namespace App\Services;

use App\Pipeline\RunnerFactory;
use App\DTOs\PipelineStepResult;
use App\Models\CostLog;
use App\Models\PipelineStep;
use App\Models\Project;

class PipelineStepRunnerService
{
    public function __construct(
        private readonly AnthropicClient $anthropic,
    ) {}

    public function run(PipelineStep $step): PipelineStepResult
    {
        $step->loadMissing('task.project.user');
        $project = $step->task->project;
        $user = $project->user;

        if (! $user?->claude_api_key) {
            throw new \RuntimeException('Clé API Claude manquante pour cet utilisateur.');
        }

        $runner = RunnerFactory::make($step->role, $project);
        $model = PipelineRoleCapabilities::resolveModel($step->role, $project, $step);
        $timeout = (int) config('maestro.anthropic_timeout', 180);
        $maxTokens = (int) config("maestro.role_max_tokens.{$step->role}", 4096);

        set_time_limit($timeout + 60);

        $response = $this->anthropic->createMessage(
            apiKey: $user->claude_api_key,
            model: $model,
            systemBlocks: [
                [
                    'type' => 'text',
                    'text' => $this->buildProjectContext($project),
                    'cache_control' => ['type' => 'ephemeral'],
                ],
                [
                    'type' => 'text',
                    'text' => $runner->systemPrompt(),
                ],
            ],
            userMessage: $runner->buildPrompt($step),
            maxTokens: $maxTokens,
            timeoutSeconds: $timeout,
        );

        $usage = $response['usage'];
        $cost = $this->calculateCost(
            $model,
            $usage['input_tokens'],
            $usage['output_tokens'],
            $usage['cache_read_input_tokens'],
        );

        CostLog::create([
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'task_id' => $step->task_id,
            'pipeline_step_id' => $step->id,
            'month' => now()->startOfMonth(),
            'input_tokens' => $usage['input_tokens'],
            'output_tokens' => $usage['output_tokens'],
            'cached_tokens' => $usage['cache_read_input_tokens'],
            'cost' => $cost,
            'model' => $model,
        ]);

        return new PipelineStepResult(
            output: $response['text'],
            inputTokens: $usage['input_tokens'],
            outputTokens: $usage['output_tokens'],
            cachedTokens: $usage['cache_read_input_tokens'],
            cost: $cost,
        );
    }

    public function buildProjectContext(Project $project): string
    {
        $ctx = $project->context ?? [];

        return <<<TEXT
        ## Contexte du projet : {$project->name}

        ### Vision produit
        {$this->contextValue($ctx, 'vision')}

        ### Stack technique
        {$this->contextValue($ctx, 'stack')}

        ### Conventions de code
        {$this->contextValue($ctx, 'conventions')}

        ### Modules existants
        {$this->contextValue($ctx, 'modules')}

        ### Design system
        {$this->contextValue($ctx, 'design_system')}

        ### Contraintes absolues
        {$this->contextValue($ctx, 'constraints')}
        TEXT;
    }

    public function calculateCost(string $model, int $inputTokens, int $outputTokens, int $cachedTokens = 0): float
    {
        $prices = config('maestro.model_prices', []);
        $defaults = config('maestro.model_prices.claude-sonnet-4-6', [
            'input' => 0.000003,
            'output' => 0.000015,
            'cache' => 0.0000003,
        ]);

        $p = $prices[$model] ?? $defaults;

        return ($inputTokens * $p['input'])
            + ($outputTokens * $p['output'])
            + ($cachedTokens * $p['cache']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextValue(array $context, string $key): string
    {
        $value = $context[$key] ?? '';

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }

        return (string) $value;
    }
}
