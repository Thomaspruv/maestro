<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'description',
        'github_repo',
        'github_branch',
        'github_token',
        'context',
        'pipeline_config',
        'gate_config',
        'default_modes',
        'model_config',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if (empty($project->uuid)) {
                $project->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->user();
    }

    /**
     * @return HasMany<ProjectAgent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(ProjectAgent::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<ChangelogEntry, $this>
     */
    public function changelogEntries(): HasMany
    {
        return $this->hasMany(ChangelogEntry::class);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function hasGithubAccess(): bool
    {
        return filled($this->resolvedGithubToken());
    }

    public function resolvedGithubToken(): ?string
    {
        $this->loadMissing('user');

        return $this->github_token ?? $this->user?->github_token;
    }

    protected function casts(): array
    {
        return [
            'github_token' => 'encrypted',
            'context' => 'array',
            'pipeline_config' => 'array',
            'gate_config' => 'array',
            'default_modes' => 'array',
            'model_config' => 'array',
            'status' => ProjectStatus::class,
        ];
    }
}
