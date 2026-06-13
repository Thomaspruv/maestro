<?php

namespace App\Agents;

use App\Models\AgentRun;

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
                $sections[] = "### {$agent}\n{$output}";
            }
        }

        return implode("\n\n", $sections);
    }
}
