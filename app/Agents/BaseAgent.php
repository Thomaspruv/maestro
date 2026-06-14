<?php

namespace App\Agents;

use App\Models\AgentRun;
use App\Services\DevPromptBuilder;

abstract class BaseAgent
{
    public function __construct(
        protected readonly string $systemPromptText,
    ) {}

    public function systemPrompt(): string
    {
        return $this->systemPromptText;
    }

    public function buildPrompt(AgentRun $run): string
    {
        $task = $run->task;
        $inputs = $run->input ?? [];
        $condenser = app(DevPromptBuilder::class);

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
            $sections[] = "\n## Outputs des agents précédents";

            foreach ($inputs as $agent => $output) {
                if ($agent === 'feedback' || ! is_string($output)) {
                    continue;
                }

                $sections[] = "### {$agent}\n".$condenser->condense($output);
            }

            if (isset($inputs['feedback']) && is_string($inputs['feedback'])) {
                $sections[] = "### feedback\n".$condenser->condense($inputs['feedback']);
            }
        }

        return implode("\n\n", $sections);
    }
}
