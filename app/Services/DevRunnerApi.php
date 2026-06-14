<?php

namespace App\Services;

use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Support\Str;

class DevRunnerApi
{
    public function __construct(
        private readonly AnthropicClient $anthropic,
        private readonly DevOutputStreamer $streamer,
    ) {}

    public function implement(AgentRun $run, string $repoPath, string $prompt, Project $project): string
    {
        $project->loadMissing('user');
        $apiKey = $project->user?->claude_api_key;

        if (! filled($apiKey)) {
            throw new \RuntimeException(
                'Clé API Claude manquante. Renseignez-la dans Paramètres → Clé API Claude.'
            );
        }

        $model = AgentCapabilities::resolveModel('dev', $project, $run);
        $tools = new DevRepoTools($repoPath);
        $maxIterations = (int) config('maestro.dev_api_max_iterations', 40);

        /** @var array<int, array<string, mixed>> $messages */
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        $log = ["Mode Dev : API Anthropic + tools\n"];

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $response = $this->anthropic->createMessageWithTools(
                apiKey: $apiKey,
                model: $model,
                systemBlocks: [[
                    'type' => 'text',
                    'text' => $this->systemPrompt(),
                ]],
                messages: $messages,
                tools: $tools->definitions(),
                maxTokens: 8192,
                timeoutSeconds: (int) config('maestro.dev_api_timeout', 120),
            );

            $log[] = '--- Itération '.($iteration + 1)." ({$response['stop_reason']}) ---";

            if (filled($response['text'])) {
                $log[] = Str::limit($response['text'], 500);
            }

            $this->streamer->flush($run, implode("\n", $log));

            if ($response['stop_reason'] === 'end_turn') {
                break;
            }

            if ($response['stop_reason'] !== 'tool_use') {
                $log[] = 'Arrêt inattendu : '.$response['stop_reason'];
                break;
            }

            /** @var array<int, array<string, mixed>> $assistantContent */
            $assistantContent = $response['content'];
            $messages[] = ['role' => 'assistant', 'content' => $assistantContent];

            /** @var array<int, array<string, mixed>> $toolResults */
            $toolResults = [];

            foreach ($assistantContent as $block) {
                if (($block['type'] ?? '') !== 'tool_use') {
                    continue;
                }

                $toolName = (string) ($block['name'] ?? '');
                /** @var array<string, mixed> $toolInput */
                $toolInput = is_array($block['input'] ?? null) ? $block['input'] : [];

                try {
                    $result = $tools->execute($toolName, $toolInput);
                    $log[] = "✓ {$toolName}(".json_encode($toolInput, JSON_UNESCAPED_UNICODE).')';
                    $log[] = Str::limit($result, 400);
                } catch (\Throwable $e) {
                    $result = 'Erreur : '.$e->getMessage();
                    $log[] = "✗ {$toolName} : ".$e->getMessage();
                }

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'] ?? '',
                    'content' => $result,
                ];
            }

            if ($toolResults === []) {
                break;
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
            $this->streamer->flush($run, implode("\n", $log));
        }

        $final = implode("\n", $log);
        $this->streamer->flush($run, $final, force: true);

        return $final;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Tu es l'agent Dev de Maestro. Tu modifies le dépôt local via les tools read_file, write_file, list_dir et bash.
- Applique uniquement les changements demandés.
- Respecte la stack TALL (Laravel, Livewire, Tailwind).
- Utilise bash pour vérifier (npm run build, pest) si nécessaire.
- Quand tu as terminé, réponds brièvement en texte sans appeler d'autres tools.
PROMPT;
    }
}
