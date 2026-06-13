<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'name',
        'emoji',
        'system_prompt',
        'model',
        'is_builtin',
        'prompt_customized',
        'sort_order',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ProjectAgent, $this>
     */
    public function projectAgents(): HasMany
    {
        return $this->hasMany(ProjectAgent::class);
    }

    /**
     * @return array{emoji: string, name: string}
     */
    public function label(): array
    {
        return [
            'emoji' => $this->emoji,
            'name' => $this->name,
        ];
    }

    protected function casts(): array
    {
        return [
            'is_builtin' => 'boolean',
            'prompt_customized' => 'boolean',
        ];
    }
}
