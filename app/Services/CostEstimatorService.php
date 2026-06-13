<?php

namespace App\Services;

use App\Models\Task;

class CostEstimatorService
{
    public function estimate(Task $task): array
    {
        $task->loadMissing('project');
        $pipeline = app(OrchestratorService::class)->getPipelineForTask($task);
        $estimates = [];
        $total = 0.0;

        $projectContextTokens = (int) config('maestro.project_context_tokens', 2000);
        $avgOutputTokens = config('maestro.avg_output_tokens', []);

        foreach ($pipeline as $index => $agent) {
            $model = $this->resolveModel($task, $agent);
            $inputTokens = $projectContextTokens
                + $this->estimateAccumulatedContext($index)
                + 500;
            $outputTokens = (int) ($avgOutputTokens[$agent] ?? 500);

            $cachedContextCost = $index > 0
                ? $projectContextTokens * $this->price($model, 'cache')
                : $projectContextTokens * $this->price($model, 'input');

            $cost = $cachedContextCost
                + (($inputTokens - $projectContextTokens) * $this->price($model, 'input'))
                + ($outputTokens * $this->price($model, 'output'));

            $estimates[$agent] = [
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'estimated_cost' => round($cost, 5),
            ];

            $total += $cost;
        }

        return [
            'agents' => $estimates,
            'total_low' => round($total * 0.8, 4),
            'total_high' => round($total * 1.5, 4),
            'total_mid' => round($total, 4),
            'caching_note' => 'Prompt caching actif — économie estimée ~70% sur le contexte projet',
        ];
    }

    private function price(string $model, string $type): float
    {
        $prices = config('maestro.model_prices', []);
        $fallback = $prices['claude-sonnet-4-6'] ?? [
            'input' => 0.000003,
            'output' => 0.000015,
            'cache' => 0.0000003,
        ];

        $modelPrices = $prices[$model] ?? $fallback;

        return match ($type) {
            'input' => (float) $modelPrices['input'],
            'output' => (float) $modelPrices['output'],
            'cache' => (float) $modelPrices['cache'],
            default => (float) $fallback['input'],
        };
    }

    private function estimateAccumulatedContext(int $agentIndex): int
    {
        return $agentIndex * 500;
    }

    private function resolveModel(Task $task, string $agent): string
    {
        $modelConfig = $task->project->model_config ?? [];

        return $modelConfig[$agent]
            ?? config("maestro.default_models.{$agent}")
            ?? 'claude-sonnet-4-6';
    }
}
