<?php

namespace App\Services;

use App\Enums\PrStatus;
use App\Enums\TaskStatus;
use App\Jobs\RunAgentJob;
use App\Models\Project;
use App\Models\Task;
use Github\AuthMethod;
use Github\Client;
use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    public function createBranch(Project $project, string $branchName): void
    {
        [$owner, $repo] = $this->parseRepo($project->github_repo);
        $sha = $this->getDefaultBranchSha($project);

        $this->client($project)->git()->references()->create($owner, $repo, [
            'ref' => "refs/heads/{$branchName}",
            'sha' => $sha,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function openPullRequest(Task $task, string $branch, string $description): array
    {
        [$owner, $repo] = $this->parseRepo($task->project->github_repo);

        $pr = $this->client($task->project)->pullRequests()->create($owner, $repo, [
            'title' => "[{$task->type->value}] {$task->title}",
            'body' => $description,
            'head' => $branch,
            'base' => $task->project->github_branch,
        ]);

        $task->update([
            'github_branch' => $branch,
            'github_pr_url' => $pr['html_url'] ?? null,
            'github_pr_number' => $pr['number'] ?? null,
            'pr_status' => PrStatus::Open,
            'status' => TaskStatus::InReview,
        ]);

        return $pr;
    }

    public function syncPrStatus(Task $task): void
    {
        if (! $task->github_pr_number) {
            return;
        }

        [$owner, $repo] = $this->parseRepo($task->project->github_repo);

        $pr = $this->client($task->project)->pullRequests()->show(
            $owner,
            $repo,
            $task->github_pr_number,
        );

        $status = ($pr['merged'] ?? false)
            ? PrStatus::Merged
            : (($pr['state'] ?? 'open') === 'closed' ? PrStatus::Closed : PrStatus::Open);

        $task->update(['pr_status' => $status]);

        if ($status === PrStatus::Merged) {
            $task->update(['status' => TaskStatus::Done]);
            RunAgentJob::dispatch($task->fresh(), 'doc');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRepos(string $token): array
    {
        $client = new Client;
        $client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);

        return $client->currentUser()->repositories('owner', 'updated', 'desc', 1, 100);
    }

    public function getFileContents(Project $project, string $path, ?string $reference = null): ?string
    {
        [$owner, $repo] = $this->parseRepo($project->github_repo);

        try {
            $content = $this->client($project)->repos()->contents()->show(
                $owner,
                $repo,
                $path,
                $reference ?? $project->github_branch,
            );

            if (! isset($content['content'])) {
                return null;
            }

            $decoded = base64_decode((string) $content['content'], true);

            return $decoded !== false ? $decoded : null;
        } catch (RuntimeException $e) {
            Log::debug('Fichier GitHub introuvable', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function client(Project $project): Client
    {
        $client = new Client;
        $client->authenticate($project->github_token, null, AuthMethod::ACCESS_TOKEN);

        return $client;
    }

    private function getDefaultBranchSha(Project $project): string
    {
        [$owner, $repo] = $this->parseRepo($project->github_repo);

        $ref = $this->client($project)->git()->references()->show(
            $owner,
            $repo,
            "heads/{$project->github_branch}",
        );

        return (string) ($ref['object']['sha'] ?? '');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseRepo(string $githubRepo): array
    {
        $parts = explode('/', $githubRepo, 2);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Format de repo GitHub invalide : {$githubRepo}");
        }

        return [$parts[0], $parts[1]];
    }
}
