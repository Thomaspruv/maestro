<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'claude_api_key', 'github_token', 'github_username', 'github_connected_at', 'monthly_budget', 'notification_preferences'])]
#[Hidden(['password', 'remember_token', 'claude_api_key', 'github_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<UserAgent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(UserAgent::class);
    }

    /**
     * @return HasMany<CostLog, $this>
     */
    public function costLogs(): HasMany
    {
        return $this->hasMany(CostLog::class);
    }

    /**
     * @return HasMany<McpToken, $this>
     */
    public function mcpTokens(): HasMany
    {
        return $this->hasMany(McpToken::class);
    }

    public function hasGithubConnection(): bool
    {
        return filled($this->github_token);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'claude_api_key' => 'encrypted',
            'github_token' => 'encrypted',
            'github_connected_at' => 'datetime',
            'monthly_budget' => 'decimal:2',
            'notification_preferences' => 'array',
        ];
    }
}
