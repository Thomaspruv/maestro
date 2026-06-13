<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'project_id',
        'task_id',
        'agent_run_id',
        'month',
        'input_tokens',
        'output_tokens',
        'cached_tokens',
        'cost',
        'model',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'cost' => 'decimal:6',
        ];
    }
}
