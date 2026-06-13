<?php

namespace App\Http\Controllers\Gates;

use App\Enums\GateStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gates\RejectGateRequest;
use App\Jobs\RunAgentJob;
use App\Models\Gate;
use App\Services\OrchestratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GateController extends Controller
{
    public function approve(Request $request, Gate $gate, OrchestratorService $orchestrator): RedirectResponse
    {
        $this->authorize('update', $gate);

        $gate->update([
            'status' => GateStatus::Approved,
            'reviewed_at' => now(),
        ]);

        if ($request->filled('edited_output')) {
            $gate->agentRun->update(['edited_output' => $request->input('edited_output')]);
        }

        $orchestrator->advance($gate->task->fresh());

        return back()->with('success', 'Gate validée — pipeline relancé.');
    }

    public function reject(RejectGateRequest $request, Gate $gate): RedirectResponse
    {
        $maxRegenerations = (int) config('maestro.max_gate_regenerations', 2);

        if ($gate->regeneration_count >= $maxRegenerations) {
            $gate->update(['status' => GateStatus::Rejected]);
            $gate->task->update(['status' => TaskStatus::Failed]);

            return back()->with('error', 'Maximum de régénérations atteint. Intervention manuelle requise.');
        }

        $gate->update([
            'status' => GateStatus::Pending,
            'feedback' => $request->validated('feedback'),
            'regeneration_count' => $gate->regeneration_count + 1,
        ]);

        RunAgentJob::dispatch(
            $gate->task,
            $gate->agentRun->agent_type->value,
            feedback: $request->validated('feedback'),
        );

        return back()->with('success', 'Feedback envoyé — l\'agent régénère.');
    }
}
