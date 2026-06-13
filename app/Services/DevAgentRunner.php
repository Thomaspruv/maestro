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
    ) {}

    public function run(AgentRun $run): AgentResult
    {
        $run->loadMissing('task.project');
        $project = $run->task->project;
        $repoPath = $this->cloneOrPull($project);
        $branch = $this->buildBranchName($run);

        $this->github->createBranch($project, $branch);
        $this->checkoutBranch($repoPath, $branch);

        $this->runClaudeCode($repoPath, $this->buildDevPrompt($run));

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
                $this->runClaudeCode(
                    $repoPath,
                    "Les validations ont échoué :\n{$validation->errorsAsString()}\n\nCorrige le code pour que tout passe.",
                );
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

    private function buildBranchName(AgentRun $run): string
    {
        return 'feature/'.$run->task->uuid.'-'.Str::slug($run->task->title);
    }

    private function buildDevPrompt(AgentRun $run): string
    {
        $inputs = $run->input ?? [];
        $sections = [
            "## Tâche : {$run->task->title}",
            $run->task->description ?? '',
        ];

        if ($inputs !== []) {
            $sections[] = '## Contexte des agents précédents';

            foreach ($inputs as $agent => $output) {
                $sections[] = "### {$agent}\n{$output}";
            }
        }

        $sections[] = 'Implémente les changements dans le dépôt local. Respecte les conventions du projet.';

        return implode("\n\n", $sections);
    }

    private function checkoutBranch(string $repoPath, string $branch): void
    {
        Process::path($repoPath)->run(['git', 'checkout', '-B', $branch])->throw();
    }

    private function pushBranch(string $repoPath, string $branch): void
    {
        Process::path($repoPath)->run(['git', 'push', '-u', 'origin', $branch])->throw();
    }

    private function runClaudeCode(string $repoPath, string $prompt): void
    {
        Process::path($repoPath)
            ->timeout(180)
            ->run(['claude', '--print', '--dangerously-skip-permissions', $prompt])
            ->throw();
    }
}
