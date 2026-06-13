<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\GitHubService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GitHubWebhookController extends Controller
{
    public function __invoke(Request $request, GitHubService $github): Response
    {
        if ($request->input('action') === 'closed' && $request->boolean('pull_request.merged')) {
            $prNumber = $request->input('pull_request.number');

            $task = Task::query()
                ->where('github_pr_number', $prNumber)
                ->first();

            if ($task) {
                $github->syncPrStatus($task);
            }
        }

        return response()->noContent();
    }
}
