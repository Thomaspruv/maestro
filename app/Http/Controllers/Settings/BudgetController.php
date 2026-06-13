<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBudgetRequest;
use App\Models\CostLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class BudgetController extends Controller
{
    public function update(UpdateBudgetRequest $request): RedirectResponse
    {
        $request->user()->update([
            'monthly_budget' => $request->validated('monthly_budget'),
        ]);

        return back()->with('success', 'Budget mensuel mis à jour.');
    }

    public function currentMonthCost(User $user): float
    {
        return (float) CostLog::query()
            ->where('user_id', $user->id)
            ->where('month', now()->startOfMonth()->toDateString())
            ->sum('cost');
    }
}
