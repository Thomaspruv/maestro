<?php

namespace App\Livewire;

use App\Enums\AgentRunStatus;
use App\Models\Task;
use App\Services\OrchestratorService;
use App\Services\PipelineHealthService;
use App\Services\ProjectAgentSyncService;
use App\Support\PipelineActivity;
use Livewire\Attributes\On;
use Livewire\Component;

class TaskPipeline extends Component
{
    public Task $task;

    public ?int $selectedRunId = null;

    public function mount(Task $task): void
    {
        $this->task = $task->load(['agentRuns', 'gates', 'project']);
        $this->syncSelectionToActiveAgent();
    }

    #[On('echo:task.{task.id},AgentRunUpdated')]
    public function onAgentRunUpdated(): void
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
        $this->task->refresh()->load(['agentRuns', 'gates', 'project']);
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
        $this->task->update(['status' => \App\Enums\TaskStatus::InProgress, 'current_agent' => null]);
        app(OrchestratorService::class)->advance($this->task->fresh());
        $this->refreshTask();
    }

    public function render()
    {
        $orchestrator = app(OrchestratorService::class);
        $pipeline = $orchestrator->getPipelineForTask($this->task);
        $runsByAgent = $this->task->agentRuns->keyBy(fn ($r) => $r->agent_type);
        $pendingGates = $this->task->gates->where('status', 'pending');
        $health = app(PipelineHealthService::class)->forTask($this->task, $pipeline);

        $labelService = app(ProjectAgentSyncService::class);
        $agentLabels = $labelService->resolveLabelsForUser($this->task->project->user);

        return view('livewire.task-pipeline', [
            'pipeline' => $pipeline,
            'runsByAgent' => $runsByAgent,
            'pendingGates' => $pendingGates,
            'agentLabels' => $agentLabels,
            'health' => $health,
            'shouldPoll' => PipelineActivity::shouldPoll($this->task),
            'currentAgent' => $health['current_agent'],
        ]);
    }

    private function syncSelectionToActiveAgent(): void
    {
        $active = PipelineActivity::runningRun($this->task)
            ?? PipelineActivity::pendingRun($this->task);

        if ($active && $this->selectedRunId !== $active->id) {
            $this->selectedRunId = $active->id;
            $this->dispatch('agent-selected', runId: $active->id);
        } elseif (! $this->selectedRunId && $this->task->agentRuns->isNotEmpty()) {
            $last = $this->task->agentRuns->sortByDesc('id')->first();
            $this->selectedRunId = $last?->id;
            if ($last) {
                $this->dispatch('agent-selected', runId: $last->id);
            }
        }
    }
}
