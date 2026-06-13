<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPromptHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'project_agent_id',
        'system_prompt',
    ];

    /**
     * @return BelongsTo<ProjectAgent, $this>
     */
    public function projectAgent(): BelongsTo
    {
        return $this->belongsTo(ProjectAgent::class);
    }
}
