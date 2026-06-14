<?php

namespace App\Services;

use App\DTOs\AgentResult;
use App\Exceptions\DevAgentMaxAttemptsException;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class DevAgentRunner
{
    public function __construct(
        private readonly GitHubService $github,
        private readonly DevPromptBuilder $promptBuilder,
        private readonly DevRunnerApi $runnerApi,
        private readonly DevOutputStreamer $streamer,
    ) {}

    public function run(AgentRun $run): AgentResult
    {
        $run->loadMissing('task.project');
        $project = $run->task->project;
        $repoPath = $this->cloneOrPull($project);
        $branch = $this->buildBranchName($run);

        $this->github->createBranch($project, $branch);
        $this->checkoutBranch($repoPath, $branch);

        $prompt = $this->promptBuilder->build($run);
        $this->implementCode($run, $repoPath, $prompt, $project);

        $maxAttempts = (int) config('maestro.max_dev_attempts', 3);
        $validation = new ValidationResult(false, ['init' => 'Validation non exécutée']);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $validation = $this->validate($repoPath);

            if ($validation->passes()) {
                $this->pushBranch($repoPath, $branch);

                $run->task->update(['github_branch' => $branch]);

                return new AgentResult(
                    output: "Branche : `{$branch}`\nTests : {$validation->summary()}",
                    prBranch: $branch,
                );
            }

            if ($attempt < $maxAttempts) {
                $fixPrompt = "Les validations ont échoué :\n{$validation->errorsAsString()}\n\nCorrige le code pour que tout passe.";
                $this->implementCode($run, $repoPath, $fixPrompt, $project);
            }
        }

        throw new DevAgentMaxAttemptsException(
            "Échec après {$maxAttempts} tentatives.\n\n".$validation->errorsAsString(),
        );
    }

    public function cloneOrPull(Project $project): string
    {
        $project->loadMissing('user');
        $github = app(GitHubConnectionService::class);
        [$owner, $repo] = $github->parseRepo($project->github_repo);
        $path = config('maestro.repos_path').'/'.Str::of("{$owner}/{$repo}")->replace('/', '_');

        if (! is_dir($path)) {
            $url = $github->repoCloneUrl($project, $project->user);
            Process::run(['git', 'clone', $url, $path])->throw();
        } else {
            Process::path($path)->run(['git', 'pull', 'origin', $project->github_branch])->throw();
        }

        return $path;
    }

    public function validate(string $repoPath): ValidationResult
    {
        $errors = [];

        $artisan = Process::path($repoPath)->run(['php', 'artisan', '--version']);
        if ($artisan->failed()) {
            $errors['php'] = trim($artisan->errorOutput());
        }

        $pest = Process::path($repoPath)->run(['./vendor/bin/pest', '--no-coverage', '--compact']);
        if ($pest->failed()) {
            $errors['tests'] = trim($pest->output());
        }

        $npm = Process::path($repoPath)->run(['npm', 'run', 'build']);
        if ($npm->failed()) {
            $errors['frontend'] = trim($npm->errorOutput());
        }

        return new ValidationResult($errors === [], $errors);
    }

    private function implementCode(AgentRun $run, string $repoPath, string $prompt, Project $project): void
    {
        if ($this->usesApiRunner()) {
            $this->runnerApi->implement($run, $repoPath, $prompt, $project);

            return;
        }

        $this->runClaudeCode($run, $repoPath, $prompt, $project);
    }

    private function usesApiRunner(): bool
    {
        return config('maestro.dev_runner', 'cli') === 'api';
    }

    private function buildBranchName(AgentRun $run): string
    {
        return 'feature/'.$run->task->uuid.'-'.Str::slug($run->task->title);
    }

    private function checkoutBranch(string $repoPath, string $branch): void
    {
        Process::path($repoPath)->run(['git', 'checkout', '-B', $branch])->throw();
    }

    private function pushBranch(string $repoPath, string $branch): void
    {
        Process::path($repoPath)->run(['git', 'push', '-u', 'origin', $branch])->throw();
    }

    private function runClaudeCode(AgentRun $run, string $repoPath, string $prompt, Project $project): void
    {
        $project->loadMissing('user');
        $apiKey = $project->user?->claude_api_key;

        if (! filled($apiKey)) {
            throw new \RuntimeException(
                'Clé API Claude manquante. Renseignez-la dans Paramètres → Clé API Claude — l\'agent Dev l\'utilise aussi.'
            );
        }

        $binary = $this->resolveClaudeBinary();
        $model = AgentCapabilities::resolveModel('dev', $project, $run);
        $promptFile = $this->writePromptFile($run, $prompt);
        $timeout = (int) config('maestro.dev_claude_timeout', 900);
        $output = '';

        try {
            Process::path($repoPath)
                ->timeout($timeout)
                ->env(['ANTHROPIC_API_KEY' => $apiKey])
                ->run(
                    ['bash', '-c', 'cat '.escapeshellarg($promptFile).' | '.escapeshellarg($binary).' --print --model '.escapeshellarg($model).' --dangerously-skip-permissions'],
                    function (string $type, string $buffer) use ($run, &$output) {
                        $output .= $buffer;
                        $this->streamer->flush($run, $output);
                    },
                )
                ->throw();
        } finally {
            @unlink($promptFile);
        }

        $this->streamer->flush($run, $output, force: true);
    }

    private function writePromptFile(AgentRun $run, string $prompt): string
    {
        $directory = storage_path('app/dev-prompts');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier dev-prompts.');
        }

        $path = $directory.'/'.$run->id.'_'.uniqid('', true).'.md';
        file_put_contents($path, $prompt);

        return $path;
    }

    private function resolveClaudeBinary(): string
    {
        $configured = config('maestro.claude_code_path');

        if (filled($configured)) {
            if (! is_executable($configured)) {
                throw new \RuntimeException(
                    "Claude Code introuvable à {$configured}. Vérifiez CLAUDE_CODE_PATH dans .env."
                );
            }

            return $configured;
        }

        $home = getenv('HOME') ?: '';
        $candidates = glob($home.'/Library/Application Support/Claude/claude-code/*/claude.app/Contents/MacOS/claude') ?: [];
        rsort($candidates);

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        foreach (['claude', '/usr/local/bin/claude', $home.'/.local/bin/claude'] as $candidate) {
            $resolved = trim((string) shell_exec('command -v '.escapeshellarg($candidate).' 2>/dev/null'));

            if ($resolved !== '' && is_executable($resolved)) {
                return $resolved;
            }
        }

        throw new \RuntimeException(
            'CLI Claude Code introuvable. Installez Claude Code, définissez CLAUDE_CODE_PATH, ou passez MAESTRO_DEV_RUNNER=api.'
        );
    }
}
