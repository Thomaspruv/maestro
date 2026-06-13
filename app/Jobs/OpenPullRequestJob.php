<?php

namespace App\Jobs;

use App\Enums\AgentRunStatus;
use App\Enums\AgentType;
use App\Models\Task;
use App\Services\GitHubService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class OpenPullRequestJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Task $task,
    ) {
        $this->onQueue('agents');
    }

    public function handle(GitHubService $github, NotificationService $notifications): void
    {
        $this->task->loadMissing('project');

        if ($this->task->github_pr_url) {
            return;
        }

        $branch = $this->task->github_branch;

        if (! $branch) {
            Log::warning('OpenPullRequestJob : branche GitHub manquante', ['task_id' => $this->task->id]);

            return;
        }

        $prExpertRun = $this->task->agentRuns()
            ->where('agent_type', AgentType::PrExpert)
            ->where('status', AgentRunStatus::Completed)
            ->latest()
            ->first();

        if (! $prExpertRun) {
            Log::warning('OpenPullRequestJob : run PR Expert introuvable', ['task_id' => $this->task->id]);

            return;
        }

        $description = $prExpertRun->edited_output ?? $prExpertRun->output ?? '';
        $pr = $github->openPullRequest($this->task, $branch, $description);

        $notifications->notifyPrReady($this->task->fresh(), $pr);
    }
}
