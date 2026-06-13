<?php

namespace App\Http\Controllers\Costs;

use App\Http\Controllers\Controller;
use App\Models\CostLog;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CostController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        $logs = CostLog::query()
            ->where('project_id', $project->id)
            ->with(['task', 'agentRun'])
            ->latest('created_at')
            ->paginate(50);

        $monthlyTotals = CostLog::query()
            ->where('project_id', $project->id)
            ->select('month', DB::raw('SUM(cost) as total_cost'))
            ->groupBy('month')
            ->orderByDesc('month')
            ->get();

        return view('costs.index', compact('project', 'logs', 'monthlyTotals'));
    }

    public function global(): View
    {
        $user = auth()->user();

        $logs = CostLog::query()
            ->where('user_id', $user->id)
            ->with(['project', 'task', 'agentRun'])
            ->latest('created_at')
            ->paginate(50);

        $monthlyTotals = CostLog::query()
            ->where('user_id', $user->id)
            ->select('month', DB::raw('SUM(cost) as total_cost'))
            ->groupBy('month')
            ->orderByDesc('month')
            ->get();

        $currentMonthCost = (float) CostLog::query()
            ->where('user_id', $user->id)
            ->where('month', now()->startOfMonth()->toDateString())
            ->sum('cost');

        return view('costs.global', compact('logs', 'monthlyTotals', 'currentMonthCost'));
    }
}
