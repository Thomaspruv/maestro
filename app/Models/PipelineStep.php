<?php

namespace App\Models;

use App\Enums\PipelineStepStatus;
use Database\Factories\PipelineStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStep extends Model
{
    /** @use HasFactory<PipelineStepFactory> */
    use HasFactory;

    protected $table = 'pipeline_steps';

    protected $fillable = [
        'task_id',
        'role',
        'status',
        'input',
        'output',
        'edited_output',
        'model',
        'input_tokens',
        'output_tokens',
        'cached_tokens',
        'cost',
        'attempt',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return HasMany<Gate, $this>
     */
    public function gates(): HasMany
    {
        return $this->hasMany(Gate::class);
    }

    protected function casts(): array
    {
        return [
            'status' => PipelineStepStatus::class,
            'input' => 'array',
            'cost' => 'decimal:6',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
