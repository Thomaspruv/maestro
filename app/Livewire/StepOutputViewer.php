<?php

namespace App\Livewire;

use App\Enums\GateStatus;
use App\Models\PipelineStep;
use App\Models\Gate;
use App\Models\Task;
use App\Services\GateReviewService;
use App\Services\ProjectRoleSyncService;
use App\Support\PipelineActivity;
use Livewire\Attributes\On;
use Livewire\Component;

class StepOutputViewer extends Component
{
    public Task $task;

    public ?int $selectedRunId = null;

    public bool $editMode = false;

    public string $editedOutput = '';

    public string $gateFeedback = '';

    public ?string $gateNotice = null;

    public ?string $gateNoticeTone = null;

    public function mount(Task $task, ?int $selectedRunId = null): void
    {
        $this->task = $task->load(['pipelineSteps.gates', 'gates', 'project']);
        $this->selectedRunId = $selectedRunId ?? PipelineActivity::runningRun($this->task)?->id
            ?? $this->task->pipelineSteps->sortByDesc('id')->first()?->id;
        $this->loadOutput();
    }

    #[On('agent-selected')]
    public function onAgentSelected(int $runId): void
    {
        $this->selectedRunId = $runId;
        $this->editMode = false;
        $this->loadOutput();
    }

    #[On('echo:task.{task.id},PipelineStepUpdated')]
    public function onPipelineStepUpdated(): void
    {
        $this->refreshViewer();
    }

    #[On('echo:task.{task.id},GatePending')]
    public function onGatePending(): void
    {
        $this->refreshViewer();
    }

    public function refreshViewer(): void
    {
        $this->task->refresh()->load(['pipelineSteps.gates', 'gates', 'project']);

        $running = PipelineActivity::runningRun($this->task);
        if ($running && $this->selectedRunId !== $running->id) {
            $this->selectedRunId = $running->id;
            $this->editMode = false;
        }

        $pending = PipelineActivity::pendingRun($this->task);
        if ($pending && ! PipelineActivity::runningRun($this->task) && $this->selectedRunId !== $pending->id) {
            $this->selectedRunId = $pending->id;
            $this->editMode = false;
        }

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

    public function approveGate(int $gateId, GateReviewService $gateReview): void
    {
        $gate = Gate::where('task_id', $this->task->id)->findOrFail($gateId);
        $this->authorize('update', $gate);

        $gateReview->approve($gate, $this->editedOutput !== '' ? $this->editedOutput : null);
        $this->editMode = false;
        $this->gateNotice = 'Gate validée — l\'agent suivant démarre.';
        $this->gateNoticeTone = 'success';
        $this->refreshViewer();
        $this->dispatch('gate-reviewed');
    }

    public function rejectGate(int $gateId, GateReviewService $gateReview): void
    {
        $this->validate(['gateFeedback' => ['required', 'string', 'max:5000']]);

        $gate = Gate::where('task_id', $this->task->id)->findOrFail($gateId);
        $this->authorize('update', $gate);

        $gateReview->reject($gate, $this->gateFeedback);

        $this->gateFeedback = '';
        $this->gateNotice = 'Feedback envoyé — l\'agent régénère sa réponse.';
        $this->gateNoticeTone = 'warning';
        $this->refreshViewer();
        $this->dispatch('gate-reviewed');
    }

    public function render()
    {
        $run = $this->getSelectedRun();
        $pendingGate = $run
            ? $this->task->gates->first(
                fn ($gate) => $gate->pipeline_step_id === $run->id && $gate->status === GateStatus::Pending
            )
            : null;

        $labelService = app(ProjectRoleSyncService::class);
        $agentLabels = $labelService->resolveLabelsForUser($this->task->project->user);

        return view('livewire.step-output-viewer', [
            'run' => $run,
            'pendingGate' => $pendingGate,
            'agentLabels' => $agentLabels,
            'shouldPoll' => PipelineActivity::shouldPoll($this->task),
            'activityMessage' => $run ? PipelineActivity::roleMessage($run->role) : null,
            'duration' => PipelineActivity::formatDuration($run),
        ]);
    }

    private function getSelectedRun(): ?PipelineStep
    {
        if (! $this->selectedRunId) {
            return null;
        }

        return $this->task->pipelineSteps->firstWhere('id', $this->selectedRunId);
    }

    private function loadOutput(): void
    {
        $run = $this->getSelectedRun();
        $this->editedOutput = $run?->edited_output ?? $run?->output ?? '';
    }
}
