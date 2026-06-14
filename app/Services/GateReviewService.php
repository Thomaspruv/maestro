<?php

namespace App\Services;

use App\Enums\GateStatus;
use App\Enums\TaskStatus;
use App\Events\GateStatusUpdated;
use App\Jobs\RunAgentJob;
use App\Models\Gate;

class GateReviewService
{
    public function approve(Gate $gate, ?string $editedOutput = null): void
    {
        $gate->update([
            'status' => GateStatus::Approved,
            'reviewed_at' => now(),
        ]);

        if ($editedOutput !== null && $editedOutput !== '' && $gate->agentRun) {
            $gate->agentRun->update(['edited_output' => $editedOutput]);
        }

        broadcast(new GateStatusUpdated($gate->fresh()));
        app(OrchestratorService::class)->advance($gate->task->fresh(), afterGateApproval: true);
    }

    public function reject(Gate $gate, string $feedback = 'Rejected by user'): void
    {
        $maxRegenerations = (int) config('maestro.max_gate_regenerations', 2);

        if ($gate->regeneration_count >= $maxRegenerations) {
            $gate->update(['status' => GateStatus::Rejected]);
            $gate->task->update(['status' => TaskStatus::Failed]);
            broadcast(new GateStatusUpdated($gate->fresh()));

            return;
        }

        $gate->update([
            'status' => GateStatus::Pending,
            'feedback' => $feedback,
            'regeneration_count' => $gate->regeneration_count + 1,
        ]);

        broadcast(new GateStatusUpdated($gate->fresh()));
        RunAgentJob::dispatch(
            $gate->task,
            $gate->agentRun->agent_type,
            feedback: $feedback,
        );
    }
}
