<?php

namespace App\Events;

use App\Models\Gate;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GateStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Gate $gate,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("task.{$this->gate->task_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GateStatusUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'gate_id' => $this->gate->id,
            'gate_type' => $this->gate->gate_type->value,
            'status' => $this->gate->status->value,
            'task_id' => $this->gate->task_id,
            'feedback' => $this->gate->feedback,
        ];
    }
}
