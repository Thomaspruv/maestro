<?php

namespace App\Services;

use App\Models\AgentRun;
use Illuminate\Support\Str;

class DevPromptBuilder
{
    /** ~2000 tokens (approx. 4 chars/token). */
    public const MAX_CHARS_PER_AGENT = 8000;

    public function build(AgentRun $run): string
    {
        $run->loadMissing('task');
        $inputs = $run->input ?? [];

        $sections = [
            "## Tâche : {$run->task->title}",
            $run->task->description ?? '',
        ];

        if ($inputs !== []) {
            $sections[] = '## Contexte des agents précédents (résumé)';

            foreach ($inputs as $agent => $output) {
                if ($agent === 'feedback' || ! is_string($output)) {
                    continue;
                }

                $sections[] = "### {$agent}\n".$this->condense($output);
            }
        }

        if (isset($inputs['feedback']) && is_string($inputs['feedback'])) {
            $sections[] = "### feedback\n".$this->condense($inputs['feedback']);
        }

        $sections[] = 'Implémente les changements dans le dépôt local. Respecte les conventions du projet.';

        return implode("\n\n", $sections);
    }

    public function condense(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (Str::length($text) <= self::MAX_CHARS_PER_AGENT) {
            return $text;
        }

        $half = (int) (self::MAX_CHARS_PER_AGENT / 2) - 80;

        return Str::substr($text, 0, $half)
            ."\n\n[… contenu tronqué pour le Dev Agent — consultez la sortie complète dans Maestro …]\n\n"
            .Str::substr($text, -$half);
    }
}
