<?php

namespace Database\Factories;

use App\Enums\PipelineStepStatus;
use App\Models\PipelineStep;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineStep>
 */
class PipelineStepFactory extends Factory
{
    protected $model = PipelineStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'role' => 'pm',
            'status' => PipelineStepStatus::Pending,
            'input' => [],
            'output' => null,
            'edited_output' => null,
            'model' => 'claude-sonnet-4-6',
            'input_tokens' => null,
            'output_tokens' => null,
            'cached_tokens' => null,
            'cost' => null,
            'attempt' => 1,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
