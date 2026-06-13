<?php

namespace App\Livewire;

use App\Models\AgentRun;
use App\Models\Gate;
use App\Models\Task;
use App\Services\OrchestratorService;
use Livewire\Attributes\On;
use Livewire\Component;

class AgentOutputViewer extends Component
{
    public Task $task;

    public ?int $selectedRunId = null;

    public bool $editMode = false;

    public string $editedOutput = '';

    public string $gateFeedback = '';

    public function mount(Task $task, ?int $selectedRunId = null): void
    {
        $this->task = $task->load(['agentRuns.gates', 'gates']);
        $this->selectedRunId = $selectedRunId ?? $this->task->agentRuns->last()?->id;
        $this->loadOutput();
    }

    #[On('agent-selected')]
    public function onAgentSelected(int $runId): void
    {
        $this->selectedRunId = $runId;
        $this->editMode = false;
        $this->loadOutput();
    }

    #[On('echo:task.{task.id},AgentRunUpdated')]
    public function onAgentRunUpdated(): void
    {
        $this->task->refresh()->load(['agentRuns.gates', 'gates']);
        $this->loadOutput();
    }

    public function toggleEdit(): void
    {
        $this->editMode = ! $this->editMode;
        if ($this->editMode) {
            $this->loadOutput();
        }
    }

    public function saveOutput(): void
    {
        $run = $this->getSelectedRun();
        if (! $run) {
            return;
        }

        $this->authorize('update', $this->task);

        $run->update(['edited_output' => $this->editedOutput]);

        $this->editMode = false;
        session()->flash('success', 'Output enregistré.');
    }

    public function approveGate(int $gateId): void
    {
        $gate = Gate::where('task_id', $this->task->id)->findOrFail($gateId);
        $this->authorize('update', $gate);

        $gate->update([
            'status' => \App\Enums\GateStatus::Approved,
            'reviewed_at' => now(),
        ]);

        if ($this->editedOutput && $gate->agentRun) {
            $gate->agentRun->update(['edited_output' => $this->editedOutput]);
        }

        app(OrchestratorService::class)->advance($this->task->fresh());
        $this->task->refresh()->load(['agentRuns.gates', 'gates']);
    }

    public function rejectGate(int $gateId): void
    {
        $this->validate(['gateFeedback' => ['required', 'string', 'max:5000']]);

        $gate = Gate::where('task_id', $this->task->id)->findOrFail($gateId);
        $this->authorize('update', $gate);

        $maxRegenerations = (int) config('maestro.max_gate_regenerations', 2);

        if ($gate->regeneration_count >= $maxRegenerations) {
            $gate->update(['status' => \App\Enums\GateStatus::Rejected]);
            $this->task->update(['status' => \App\Enums\TaskStatus::Failed]);

            return;
        }

        $gate->update([
            'status' => \App\Enums\GateStatus::Pending,
            'feedback' => $this->gateFeedback,
            'regeneration_count' => $gate->regeneration_count + 1,
        ]);

        \App\Jobs\RunAgentJob::dispatch(
            $this->task,
            $gate->agentRun->agent_type->value,
            feedback: $this->gateFeedback,
        );

        $this->gateFeedback = '';
        $this->task->refresh()->load(['agentRuns.gates', 'gates']);
    }

    public function render()
    {
        $run = $this->getSelectedRun();
        $pendingGate = $run
            ? $this->task->gates->where('agent_run_id', $run->id)->where('status', 'pending')->first()
            : null;

        return view('livewire.agent-output-viewer', [
            'run' => $run,
            'pendingGate' => $pendingGate,
            'agentLabels' => config('maestro.agent_labels', []),
        ]);
    }

    private function getSelectedRun(): ?AgentRun
    {
        if (! $this->selectedRunId) {
            return null;
        }

        return $this->task->agentRuns->firstWhere('id', $this->selectedRunId);
    }

    private function loadOutput(): void
    {
        $run = $this->getSelectedRun();
        $this->editedOutput = $run?->edited_output ?? $run?->output ?? '';
    }
}
