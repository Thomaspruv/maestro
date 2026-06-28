<?php

namespace App\Events;

use App\Models\PipelineStep;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PipelineStepCostRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PipelineStep $step,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("task.{$this->step->task_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PipelineStepCostRecorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'step_id' => $this->step->id,
            'role' => $this->step->role,
            'cost' => $this->step->cost,
            'task_id' => $this->step->task_id,
        ];
    }
}
