<?php

namespace App\Livewire\Concerns;

use App\Services\GitHubConnectionService;
use Illuminate\Support\Facades\Auth;

trait InteractsWithGitHubRepositories
{
    /** @var array<int, array{full_name: string, name: string, private: bool, default_branch: string, html_url: string}> */
    public array $githubRepos = [];

    public bool $githubReposLoading = false;

    public ?string $githubReposError = null;

    public function loadGithubRepositories(): void
    {
        $this->githubReposLoading = true;
        $this->githubReposError = null;

        try {
            $this->githubRepos = app(GitHubConnectionService::class)
                ->listRepositories(Auth::user());
        } catch (\Throwable $e) {
            $this->githubRepos = [];
            $this->githubReposError = $e->getMessage();
        }

        $this->githubReposLoading = false;
    }

    public function selectGithubRepository(string $fullName): void
    {
        $this->github_repo = $fullName;

        $repo = collect($this->githubRepos)->firstWhere('full_name', $fullName);

        if ($repo && filled($repo['default_branch'] ?? null)) {
            $this->github_branch = $repo['default_branch'];
        }
    }

    public function updatedGithubRepo(?string $value): void
    {
        if (! $value) {
            return;
        }

        $repo = collect($this->githubRepos)->firstWhere('full_name', $value);

        if ($repo && filled($repo['default_branch'] ?? null)) {
            $this->github_branch = $repo['default_branch'];
        }
    }

    public function normalizeGithubRepoInput(): void
    {
        if ($this->github_repo === '') {
            return;
        }

        try {
            $this->github_repo = app(GitHubConnectionService::class)
                ->normalizeRepo($this->github_repo);
        } catch (\Throwable $e) {
            $this->addError('github_repo', $e->getMessage());
        }
    }

    protected function bootGithubRepositories(): void
    {
        if (app(GitHubConnectionService::class)->isConnected(Auth::user())) {
            $this->loadGithubRepositories();
        }
    }
}
