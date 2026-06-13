<?php

namespace App\Livewire;

use App\Enums\TaskMode;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Services\CostEstimatorService;
use Livewire\Component;

class CostEstimationPanel extends Component
{
    public Project $project;

    public ?Task $task = null;

    public string $type = 'feature';

    public string $mode = 'manual';

    public string $title = '';

    public string $description = '';

    /** @var array<string, mixed>|null */
    public ?array $estimate = null;

    public function mount(Project $project, ?Task $task = null, ?string $defaultMode = null): void
    {
        $this->project = $project;

        if ($task) {
            $this->task = $task;
            $this->type = $task->type->value;
            $this->mode = $task->mode->value;
            $this->title = $task->title;
            $this->description = $task->description ?? '';
        } elseif ($defaultMode) {
            $this->mode = $defaultMode;
        } else {
            $this->mode = $this->project->default_modes[$this->type] ?? 'manual';
        }

        $this->calculate();
    }

    public function updatedType(): void
    {
        $this->mode = $this->project->default_modes[$this->type] ?? 'manual';
        $this->calculate();
    }

    public function updatedMode(): void
    {
        $this->calculate();
    }

    public function updatedTitle(): void
    {
        $this->calculate();
    }

    public function updatedDescription(): void
    {
        $this->calculate();
    }

    public function calculate(): void
    {
        $draft = $this->task ?? new Task([
            'title' => $this->title,
            'description' => $this->description,
            'type' => TaskType::from($this->type),
            'mode' => TaskMode::from($this->mode),
            'project_id' => $this->project->id,
        ]);

        if (! $this->task) {
            $draft->setRelation('project', $this->project);
        }

        $this->estimate = app(CostEstimatorService::class)->estimate($draft);
    }

    public function render()
    {
        return view('livewire.cost-estimation-panel');
    }
}
