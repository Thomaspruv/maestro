<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpOAuthAuthorizationCode extends Model
{
    protected $table = 'mcp_oauth_authorization_codes';

    protected $fillable = [
        'client_id',
        'user_id',
        'code',
        'code_challenge',
        'code_challenge_method',
        'redirect_uri',
        'scope',
        'expires_at',
        'used_at',
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
            'used_at' => 'datetime',
        ];
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
