<?php

namespace App\Livewire;

use App\Models\Task;
use Livewire\Component;

class TaskCard extends Component
{
    public Task $task;

    public function mount(Task $task): void
    {
        $this->task = $task->load(['pipelineSteps', 'gates', 'project']);
    }

    public function render()
    {
        return view('livewire.task-card');
    }
}
