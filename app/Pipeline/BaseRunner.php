<?php

namespace App\Pipeline;

use App\Models\PipelineStep;
use App\Services\PipelineOutputCondenser;

abstract class BaseRunner
{
    public function __construct(
        protected readonly string $systemPromptText,
    ) {}

    public function systemPrompt(): string
    {
        return $this->systemPromptText;
    }

    public function buildPrompt(PipelineStep $step): string
    {
        $task = $step->task;
        $inputs = $step->input ?? [];
        $condenser = app(PipelineOutputCondenser::class);

        $sections = [
            '## Tâche',
            "Titre : {$task->title}",
            'Type : '.$task->type->value,
            "Description : {$task->description}",
        ];

        if ($task->module) {
            $sections[] = "Module : {$task->module}";
        }

        if ($inputs !== []) {
            $sections[] = "\n## Outputs des étapes précédentes";

            foreach ($inputs as $role => $output) {
                if ($role === 'feedback' || ! is_string($output)) {
                    continue;
                }

                $sections[] = "### {$role}\n".$condenser->condense($output);
            }

            if (isset($inputs['feedback']) && is_string($inputs['feedback'])) {
                $sections[] = "### feedback\n".$condenser->condense($inputs['feedback']);
            }
        }

        return implode("\n\n", $sections);
    }
}
