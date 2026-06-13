<?php

namespace App\Models;

use App\Enums\GateStatus;
use App\Enums\GateType;
use Database\Factories\GateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gate extends Model
{
    /** @use HasFactory<GateFactory> */
    use HasFactory;

    protected $fillable = [
        'task_id',
        'agent_run_id',
        'gate_type',
        'status',
        'feedback',
        'regeneration_count',
        'reviewed_at',
    ];

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
            'gate_type' => GateType::class,
            'status' => GateStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
