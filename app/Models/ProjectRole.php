<?php

namespace App\Models;

use Database\Factories\ProjectRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectRole extends Model
{
    /** @use HasFactory<ProjectRoleFactory> */
    use HasFactory;

    protected $table = 'project_roles';

    protected $fillable = [
        'project_id',
        'pipeline_role_id',
        'role',
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
     * @return BelongsTo<PipelineRole, $this>
     */
    public function pipelineRole(): BelongsTo
    {
        return $this->belongsTo(PipelineRole::class);
    }

    /**
     * @return HasMany<RolePromptHistory, $this>
     */
    public function promptHistories(): HasMany
    {
        return $this->hasMany(RolePromptHistory::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
