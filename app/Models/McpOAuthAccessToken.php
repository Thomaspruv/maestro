<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpOAuthAccessToken extends Model
{
    protected $table = 'mcp_oauth_access_tokens';

    protected $fillable = [
        'client_id',
        'user_id',
        'access_token',
        'refresh_token',
        'scope',
        'expires_at',
        'refresh_expires_at',
        'last_used_at',
    ];

    /**
     * @return BelongsTo<McpOAuthClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(McpOAuthClient::class, 'client_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function isAccessTokenValid(): bool
    {
        return $this->expires_at->isFuture();
    }

    public function isRefreshTokenValid(): bool
    {
        return $this->refresh_token !== null
            && $this->refresh_expires_at !== null
            && $this->refresh_expires_at->isFuture();
    }
}
