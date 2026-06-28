<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePromptHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'role_prompt_histories';

    protected $fillable = [
        'project_role_id',
        'system_prompt',
    ];

    /**
     * @return BelongsTo<ProjectRole, $this>
     */
    public function projectRole(): BelongsTo
    {
        return $this->belongsTo(ProjectRole::class);
    }
}
