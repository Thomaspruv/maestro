<?php

namespace App\Models;

use App\Enums\PrStatus;
use App\Enums\TaskMode;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'project_id',
        'title',
        'description',
        'module',
        'type',
        'priority',
        'status',
        'mode',
        'current_agent',
        'github_branch',
        'github_pr_url',
        'github_pr_number',
        'pr_status',
        'estimated_cost',
        'actual_cost',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (Task $task): void {
            if (empty($task->uuid)) {
                $task->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<AgentRun, $this>
     */
    public function agentRuns(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    /**
     * @return HasMany<Gate, $this>
     */
    public function gates(): HasMany
    {
        return $this->hasMany(Gate::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'mode' => TaskMode::class,
            'pr_status' => PrStatus::class,
            'estimated_cost' => 'decimal:4',
            'actual_cost' => 'decimal:4',
        ];
    }
}
