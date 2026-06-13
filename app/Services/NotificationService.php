<?php

namespace App\Services;

use App\Models\AgentRun;
use App\Models\Gate;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function notifyGate(Task $task, Gate $gate): void
    {
        Log::info('Gate en attente de validation', [
            'task_id' => $task->id,
            'gate_id' => $gate->id,
            'gate_type' => $gate->gate_type->value,
        ]);

        $this->broadcastIfEnabled('gate.pending', [
            'task_id' => $task->id,
            'gate_id' => $gate->id,
        ]);
    }

    public function notifyFailure(Task $task, AgentRun $run): void
    {
        Log::error('Échec agent', [
            'task_id' => $task->id,
            'agent_run_id' => $run->id,
            'agent_type' => $run->agent_type,
            'error' => $run->error_message,
        ]);

        $this->broadcastIfEnabled('agent.failed', [
            'task_id' => $task->id,
            'agent_run_id' => $run->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $pr
     */
    public function notifyPrReady(Task $task, array $pr): void
    {
        Log::info('PR prête à merger', [
            'task_id' => $task->id,
            'pr_url' => $pr['html_url'] ?? null,
        ]);

        $this->broadcastIfEnabled('pr.ready', [
            'task_id' => $task->id,
            'pr_url' => $pr['html_url'] ?? null,
        ]);
    }

    public function notifyBudgetAlert(User $user, int $thresholdPercent, float $spent, float $budget): void
    {
        Log::warning('Alerte budget mensuel', [
            'user_id' => $user->id,
            'threshold_percent' => $thresholdPercent,
            'spent' => $spent,
            'budget' => $budget,
        ]);

        $this->broadcastIfEnabled('budget.alert', [
            'user_id' => $user->id,
            'threshold_percent' => $thresholdPercent,
            'spent' => $spent,
            'budget' => $budget,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function broadcastIfEnabled(string $event, array $payload): void
    {
        if (config('broadcasting.default') === 'null') {
            return;
        }

        Log::debug('Notification broadcast stub', ['event' => $event, 'payload' => $payload]);
    }
}
