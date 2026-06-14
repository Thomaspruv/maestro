<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;

class DiscoveryChatService
{
    public function __construct(
        private readonly AnthropicClient $anthropic,
        private readonly AgentRunnerService $runner,
        private readonly GitHubContextReader $githubReader,
        private readonly UrlCrawlerService $crawler,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{text: string, display_text: string, cost: float, proposed_tasks: array<int, array<string, mixed>>}
     */
    /**
     * Discovery complète : code repo, backlog, veille web (sources configurées).
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{text: string, display_text: string, cost: float, proposed_tasks: array<int, array<string, mixed>>}
     */
    public function launch(Project $project, User $user, array $history): array
    {
        return $this->send($project, $user, $history, $this->buildLaunchMessage($project), fullDiscovery: true);
    }

    public function send(Project $project, User $user, array $history, string $message, bool $fullDiscovery = false): array
    {
        if (! $user->claude_api_key) {
            throw new \RuntimeException('Clé API Claude non configurée. Ajoutez-la dans Paramètres.');
        }

        set_time_limit((int) config('maestro.anthropic_discovery_timeout', 180) + 60);

        $enrichedMessage = $fullDiscovery
            ? $this->enrichWithDiscoverySources($this->enrichMessageWithUrls($message, fastFetch: true))
            : $this->enrichMessageWithUrls($message);
        $model = AgentCapabilities::resolveModel('discovery', $project);
        $systemBlocks = $this->buildSystemBlocks($project, $fullDiscovery);

        $conversation = array_merge($history, [
            ['role' => 'user', 'content' => $enrichedMessage],
        ]);

        $response = $this->anthropic->createConversation(
            apiKey: $user->claude_api_key,
            model: $model,
            systemBlocks: $systemBlocks,
            messages: $conversation,
            maxTokens: 4096,
            timeoutSeconds: (int) config('maestro.anthropic_discovery_timeout', 180),
        );

        $usage = $response['usage'];
        $cost = $this->runner->calculateCost(
            $model,
            $usage['input_tokens'],
            $usage['output_tokens'],
            $usage['cache_read_input_tokens'],
        );

        $parsed = self::parseResponse($response['text']);

        return [
            'text' => $response['text'],
            'display_text' => $parsed['display_text'],
            'cost' => $cost,
            'proposed_tasks' => $parsed['proposed_tasks'],
        ];
    }

    /**
     * @return array{display_text: string, proposed_tasks: array<int, array<string, mixed>>}
     */
    public static function parseResponse(string $text): array
    {
        if (! preg_match('/<tasks>\s*(.*?)\s*<\/tasks>/s', $text, $matches)) {
            return [
                'display_text' => trim($text),
                'proposed_tasks' => [],
            ];
        }

        $displayText = trim(preg_replace('/<tasks>.*?<\/tasks>/s', '', $text) ?? $text);
        $proposedTasks = [];

        try {
            $decoded = json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                foreach ($decoded as $task) {
                    if (! is_array($task) || empty($task['title'])) {
                        continue;
                    }

                    $proposedTasks[] = [
                        'title' => (string) $task['title'],
                        'description' => (string) ($task['description'] ?? ''),
                        'type' => (string) ($task['type'] ?? 'feature'),
                        'priority' => (string) ($task['priority'] ?? 'medium'),
                        'module' => isset($task['module']) && $task['module'] !== null
                            ? (string) $task['module']
                            : null,
                        'status' => 'pending',
                    ];
                }
            }
        } catch (\JsonException) {
            // Ignore invalid JSON — display raw text only
        }

        return [
            'display_text' => $displayText,
            'proposed_tasks' => $proposedTasks,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLaunchMessage(Project $project): string
    {
        $sources = config('maestro.discovery_sources', []);
        $lines = [
            'Lance une Discovery complète pour ce projet.',
            '',
            'En une seule passe :',
            '1. Analyse le code et la documentation du repo pour comprendre le produit, ses modules et ce qui est déjà livré',
            '2. Croise avec le backlog existant pour repérer les angles morts produit',
            '3. Consulte les sources de veille marché fournies et identifie tendances et opportunités',
            '4. Propose 2 à 3 tâches produit (features ou améliorations fonctionnelles) priorisées',
            '',
            'Ne propose jamais de tâches techniques (refactor, dette, dépendances).',
        ];

        if ($sources !== []) {
            $lines[] = '';
            $lines[] = 'Sources veille à analyser :';
            foreach ($sources as $url) {
                $lines[] = $url;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSystemBlocks(Project $project, bool $includeCodeContext = false): array
    {
        $blocks = [
            [
                'type' => 'text',
                'text' => $this->buildProductContext($project),
                'cache_control' => ['type' => 'ephemeral'],
            ],
            [
                'type' => 'text',
                'text' => $this->buildTasksContext($project),
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];

        $repoContext = $this->buildProductRepoContext($project, $includeCodeContext);

        if ($repoContext !== '') {
            $blocks[] = [
                'type' => 'text',
                'text' => $repoContext,
                'cache_control' => ['type' => 'ephemeral'],
            ];
        }

        $blocks[] = [
            'type' => 'text',
            'text' => AgentCapabilities::resolveSystemPrompt('discovery', $project),
        ];

        return $blocks;
    }

    private function buildTasksContext(Project $project): string
    {
        $tasks = $project->tasks()
            ->orderBy('sort_order')
            ->get(['title', 'type', 'status', 'module', 'priority']);

        if ($tasks->isEmpty()) {
            return "## Tâches existantes\n\nAucune tâche dans le backlog pour l'instant.";
        }

        $lines = ["## Tâches existantes ({$tasks->count()})", ''];

        foreach ($tasks as $task) {
            $module = $task->module ? " · module: {$task->module}" : '';
            $lines[] = "- [{$task->status->value}] {$task->title} ({$task->type->value}, {$task->priority->value}{$module})";
        }

        return implode("\n", $lines);
    }

    private function buildProductContext(Project $project): string
    {
        $ctx = $project->context ?? [];
        $vision = trim($this->contextValue($ctx, 'vision'));
        $description = trim((string) ($project->description ?? ''));

        $sections = [
            "## Vision produit : {$project->name}",
        ];

        if ($vision !== '') {
            $sections[] = "### Vision\n{$vision}";
            $sections[] = '_Toutes les propositions doivent être alignées sur cette vision — ne pas suggérer de features hors direction produit._';
        } elseif ($description !== '') {
            $sections[] = "### Description\n{$description}";
        }

        $sections[] = '### Modules / fonctionnalités existantes';
        $sections[] = $this->contextValue($ctx, 'modules');

        $sections[] = '### Design system (cohérence UX produit)';
        $sections[] = $this->contextValue($ctx, 'design_system');

        $sections[] = '_Contexte produit uniquement — ne pas en déduire de tâches techniques ou d\'audit code._';

        return implode("\n\n", $sections);
    }

    /**
     * README (+ architecture en mode Discovery complète).
     */
    private function buildProductRepoContext(Project $project, bool $includeCodeContext = false): string
    {
        if (! $project->resolvedGithubToken() || ! $project->github_repo) {
            return '';
        }

        $token = app(GitHubConnectionService::class)->resolveToken($project->user, $project);

        if (! $token) {
            return '';
        }

        try {
            $files = $this->githubReader->read(
                $project->github_repo,
                $token,
                $project->github_branch,
            );
        } catch (\Throwable) {
            return '';
        }

        $sections = [];

        $readme = $files['readme'] ?? $files['claude_md'] ?? null;

        if ($readme !== null && trim($readme) !== '') {
            $sections[] = "## Positionnement produit (README)\n\n".$this->truncate($readme, 4000);
        }

        if ($includeCodeContext) {
            $architecture = $files['architecture'] ?? null;

            if ($architecture !== null && trim($architecture) !== '') {
                $sections[] = "## Structure & modules (architecture)\n\n".$this->truncate($architecture, 4000);
            }
        }

        if ($sections === []) {
            return '';
        }

        $sections[] = '_Contexte repo pour comprendre le produit et ses capacités — ne pas proposer de tâches techniques._';

        return implode("\n\n", $sections);
    }

    private function truncate(string $text, int $maxLength): string
    {
        return mb_strlen($text) > $maxLength
            ? mb_substr($text, 0, $maxLength).'…'
            : $text;
    }

    private function enrichWithDiscoverySources(string $message): string
    {
        $sources = config('maestro.discovery_sources', []);

        if ($sources === []) {
            return $message;
        }

        $urlsInMessage = $this->crawler->extractUrls($message);
        $missingSources = array_values(array_diff($sources, $urlsInMessage));

        if ($missingSources === []) {
            return $message;
        }

        return implode("\n", $missingSources)."\n\n".$message;
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

    private function enrichMessageWithUrls(string $message, bool $fastFetch = false): string
    {
        $urls = $this->crawler->extractUrls($message);

        if ($urls === []) {
            return $message;
        }

        $timeout = $fastFetch ? 5 : 15;
        $maxBytes = $fastFetch ? 20_000 : 50_000;
        $urls = array_slice($urls, 0, $fastFetch ? 3 : 10);

        $sections = [];

        foreach ($urls as $url) {
            $content = $this->crawler->fetch($url, $maxBytes, $timeout);

            if ($content === null) {
                $sections[] = "[Contenu de {$url}]\nImpossible de récupérer le contenu.";

                continue;
            }

            $plain = strip_tags($content);
            $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;
            $truncated = mb_strlen($plain) > 3000 ? mb_substr($plain, 0, 3000).'…' : $plain;

            $sections[] = "[Contenu de {$url}]\n{$truncated}";
        }

        return implode("\n\n", $sections)."\n\n[Question de l'utilisateur]\n".$message;
    }
}
