<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_agent_id',
        'agent_type',
        'is_active',
        'model',
        'system_prompt',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<UserAgent, $this>
     */
    public function userAgent(): BelongsTo
    {
        return $this->belongsTo(UserAgent::class);
    }

    /**
     * @return HasMany<AgentPromptHistory, $this>
     */
    public function promptHistories(): HasMany
    {
        return $this->hasMany(AgentPromptHistory::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
