<?php

namespace Database\Factories;

use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentRun>
 */
class AgentRunFactory extends Factory
{
    protected $model = AgentRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'agent_type' => 'pm',
            'status' => AgentRunStatus::Pending,
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
