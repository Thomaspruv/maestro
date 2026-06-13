<?php

namespace App\Livewire;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\CostLog;
use App\Models\Project;
use App\Models\Task;
use Livewire\Component;

class GlobalDashboard extends Component
{
    public function render()
    {
        $user = auth()->user();

        $projects = Project::query()
            ->forUser($user)
            ->where('status', ProjectStatus::Active)
            ->withCount([
                'tasks',
                'tasks as tasks_in_progress_count' => fn ($q) => $q->where('status', TaskStatus::InProgress),
                'tasks as tasks_pending_gates_count' => fn ($q) => $q->whereHas('gates', fn ($g) => $g->where('status', 'pending')),
            ])
            ->latest()
            ->get();

        $currentMonthCost = (float) CostLog::query()
            ->where('user_id', $user->id)
            ->where('month', now()->startOfMonth()->toDateString())
            ->sum('cost');

        $monthlyBudget = (float) ($user->monthly_budget ?? 0);

        $recentTasks = Task::query()
            ->whereHas('project', fn ($q) => $q->where('user_id', $user->id))
            ->with('project')
            ->latest()
            ->limit(10)
            ->get();

        $totalTasks = Task::query()
            ->whereHas('project', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        $tasksInProgress = Task::query()
            ->whereHas('project', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', TaskStatus::InProgress)
            ->count();

        return view('livewire.global-dashboard', [
            'projects' => $projects,
            'currentMonthCost' => $currentMonthCost,
            'monthlyBudget' => $monthlyBudget,
            'recentTasks' => $recentTasks,
            'totalTasks' => $totalTasks,
            'tasksInProgress' => $tasksInProgress,
        ]);
    }
}
