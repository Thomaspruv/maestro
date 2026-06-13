<?php

namespace App\Livewire;

use App\Models\Task;
use App\Services\OrchestratorService;
use Livewire\Attributes\On;
use Livewire\Component;

class TaskPipeline extends Component
{
    public Task $task;

    public ?int $selectedRunId = null;

    public function mount(Task $task): void
    {
        $this->task = $task->load(['agentRuns', 'gates', 'project']);
        $this->selectedRunId = $this->task->agentRuns->last()?->id;
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
        $pipeline = app(OrchestratorService::class)->getPipelineForTask($this->task);
        $runsByAgent = $this->task->agentRuns->keyBy(fn ($r) => $r->agent_type->value);
        $pendingGates = $this->task->gates->where('status', 'pending');

        return view('livewire.task-pipeline', [
            'pipeline' => $pipeline,
            'runsByAgent' => $runsByAgent,
            'pendingGates' => $pendingGates,
            'agentLabels' => config('maestro.agent_labels', []),
        ]);
    }
}
