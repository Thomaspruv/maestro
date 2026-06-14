<?php

namespace App\Livewire;

use App\Enums\GateStatus;
use App\Models\Gate;
use App\Models\Task;
use App\Services\GateReviewService;
use App\Services\PipelineCockpitService;
use App\Support\PipelineActivity;
use Livewire\Attributes\On;
use Livewire\Attributes\Throttle;
use Livewire\Component;

class PipelineCockpit extends Component
{
    public Task $task;

    public array $snapshot = [];

    public function mount(Task $task): void
    {
        $this->authorize('update', $task);

        $this->task = $task;
        $this->refreshSnapshot();
    }

    #[On('echo:task.{task.id},AgentRunUpdated')]
    public function onAgentRunUpdated(): void
    {
        $this->refreshSnapshot();
    }

    #[On('echo:task.{task.id},GatePending')]
    public function onGatePending(): void
    {
        $this->refreshSnapshot();
    }

    #[On('echo:task.{task.id},GateStatusUpdated')]
    public function onGateStatusUpdated(): void
    {
        $this->refreshSnapshot();
    }

    #[On('echo:task.{task.id},AgentCostRecorded')]
    public function onAgentCostRecorded(): void
    {
        $this->refreshSnapshot();
    }

    #[Throttle('1s')]
    public function refreshSnapshot(): void
    {
        $this->task->refresh();
        $this->snapshot = app(PipelineCockpitService::class)->getSnapshot($this->task);
    }

    public function approveGate(int $gateId): void
    {
        $gate = Gate::where('task_id', $this->task->id)->findOrFail($gateId);
        $this->authorize('update', $gate);

        // Verify gate exists and is in correct state
        if ($gate->status !== GateStatus::Pending) {
            $this->dispatch('error', message: 'Gate is not pending');

            return;
        }

        try {
            app(GateReviewService::class)->approve($gate);
            $this->refreshSnapshot();
            $this->dispatch('gate-reviewed');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Failed to approve gate: '.$e->getMessage());
        }
    }

    public function rejectGate(int $gateId): void
    {
        $gate = Gate::where('task_id', $this->task->id)->findOrFail($gateId);
        $this->authorize('update', $gate);

        // Verify gate exists and is in correct state
        if ($gate->status !== GateStatus::Pending) {
            $this->dispatch('error', message: 'Gate is not pending');

            return;
        }

        try {
            app(GateReviewService::class)->reject($gate);
            $this->refreshSnapshot();
            $this->dispatch('gate-reviewed');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Failed to reject gate: '.$e->getMessage());
        }
    }

    public function openAgentOutput(int $runId): void
    {
        $this->dispatch('open-agent-output', runId: $runId);
    }

    public function render()
    {
        $shouldPoll = PipelineActivity::shouldPoll($this->task);

        return view('livewire.pipeline-cockpit', [
            'shouldPoll' => $shouldPoll,
        ]);
    }
}
