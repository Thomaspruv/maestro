<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckBudgetAlertsJob implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $notifications): void
    {
        User::query()
            ->whereNotNull('monthly_budget')
            ->where('monthly_budget', '>', 0)
            ->each(function (User $user) use ($notifications): void {
                $budget = (float) $user->monthly_budget;
                $spent = (float) $user->costLogs()
                    ->where('month', now()->startOfMonth())
                    ->sum('cost');

                if ($budget <= 0) {
                    return;
                }

                $percent = ($spent / $budget) * 100;

                if ($percent >= 100) {
                    $notifications->notifyBudgetAlert($user, 100, $spent, $budget);
                } elseif ($percent >= 80) {
                    $notifications->notifyBudgetAlert($user, 80, $spent, $budget);
                }
            });
    }
}
