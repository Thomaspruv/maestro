<?php

namespace Database\Factories;

use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Models\AgentRun;
use App\Models\Gate;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gate>
 */
class GateFactory extends Factory
{
    protected $model = Gate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gate_type' => GateType::SpecsReview,
            'status' => GateStatus::Pending,
            'feedback' => null,
            'regeneration_count' => 0,
            'reviewed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Gate $gate): void {
            if (! $gate->task_id) {
                $task = Task::factory()->create();
                $gate->task_id = $task->id;
            }

            if (! $gate->agent_run_id) {
                $gate->agent_run_id = AgentRun::factory()->create([
                    'task_id' => $gate->task_id,
                ])->id;
            }
        });
    }
}
