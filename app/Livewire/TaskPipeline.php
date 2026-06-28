<?php

namespace App\Livewire;

use App\Enums\GateStatus;
use App\Enums\TaskStatus;
use App\Models\Gate;
use App\Models\Task;
use App\Services\GateReviewService;
use App\Services\OrchestratorService;
use App\Services\PipelineHealthService;
use App\Services\ProjectRoleSyncService;
use App\Support\PipelineActivity;
use Livewire\Attributes\On;
use Livewire\Component;

class TaskPipeline extends Component
{
    public Task $task;

    public ?int $selectedRunId = null;

    public function mount(Task $task): void
    {
        $this->task = $task->load(['pipelineSteps', 'gates', 'project']);
        $this->syncSelectionToActiveAgent();
    }

    #[On('echo:task.{task.id},PipelineStepUpdated')]
    public function onPipelineStepUpdated(): void
    {
        $this->refreshTask();
    }

    #[On('echo:task.{task.id},GatePending')]
    public function onGatePending(): void
    {
        $this->refreshTask();
    }

    public function refreshTask(): void
    {
        $this->task->refresh()->load(['pipelineSteps', 'gates', 'project']);
        $this->syncSelectionToActiveAgent();
    }

    public function selectRun(int $runId): void
    {
        $this->selectedRunId = $runId;
        $this->dispatch('agent-selected', runId: $runId);
    }

    public function startPipeline(): void
    {
        $this->authorize('update', $this->task);

        $orchestrator = app(OrchestratorService::class);

        if (OrchestratorService::internalPipelineEnabled()) {
            $this->task->update(['status' => TaskStatus::InProgress, 'current_role' => null]);
            $orchestrator->advance($this->task->fresh());
        } else {
            $orchestrator->handoffToHermes($this->task);
        }

        $this->refreshTask();
    }

    #[On('gate-reviewed')]
    public function onGateReviewed(): void
    {
        $this->refreshTask();
    }

    public function approveGate(int $gateId, GateReviewService $gateReview): void
    {
        $gate = Gate::where('task_id', $this->task->id)->findOrFail($gateId);
        $this->authorize('update', $gate);

        $gateReview->approve($gate);
        $this->refreshTask();
        $this->dispatch('agent-selected', runId: $gate->pipeline_step_id);
        $this->dispatch('gate-reviewed');
    }

    public function render()
    {
        $orchestrator = app(OrchestratorService::class);
        $pipeline = $orchestrator->getPipelineForTask($this->task);
        $runsByAgent = $this->task->pipelineSteps->keyBy(fn ($r) => $r->role);
        $pendingGates = $this->task->gates->filter(
            fn ($gate) => $gate->status === GateStatus::Pending
        );
        $health = app(PipelineHealthService::class)->forTask($this->task, $pipeline);

        $labelService = app(ProjectRoleSyncService::class);
        $agentLabels = $labelService->resolveLabelsForUser($this->task->project->user);

        return view('livewire.task-pipeline', [
            'pipeline' => $pipeline,
            'runsByAgent' => $runsByAgent,
            'pendingGates' => $pendingGates,
            'agentLabels' => $agentLabels,
            'health' => $health,
            'shouldPoll' => PipelineActivity::shouldPoll($this->task),
            'currentAgent' => $health['current_role'],
        ]);
    }

    private function syncSelectionToActiveAgent(): void
    {
        $active = PipelineActivity::runningRun($this->task)
            ?? PipelineActivity::pendingRun($this->task);

        if ($active && $this->selectedRunId !== $active->id) {
            $this->selectedRunId = $active->id;
            $this->dispatch('agent-selected', runId: $active->id);
        } elseif (! $this->selectedRunId && $this->task->pipelineSteps->isNotEmpty()) {
            $last = $this->task->pipelineSteps->sortByDesc('id')->first();
            $this->selectedRunId = $last?->id;
            if ($last) {
                $this->dispatch('agent-selected', runId: $last->id);
            }
        }
    }
}
