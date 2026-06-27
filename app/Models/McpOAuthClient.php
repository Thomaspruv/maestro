<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McpOAuthClient extends Model
{
    protected $table = 'mcp_oauth_clients';

    protected $fillable = [
        'client_id',
        'client_name',
        'redirect_uris',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
        ];
    }

    /**
     * @return HasMany<McpOAuthAuthorizationCode, $this>
     */
    public function authorizationCodes(): HasMany
    {
        return $this->hasMany(McpOAuthAuthorizationCode::class, 'client_id');
    }

    /**
     * @return HasMany<McpOAuthAccessToken, $this>
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(McpOAuthAccessToken::class, 'client_id');
    }

    public function allowsRedirectUri(string $redirectUri): bool
    {
        foreach ($this->redirect_uris as $registered) {
            if ($this->redirectUrisMatch($registered, $redirectUri)) {
                return true;
            }
        }

        return false;
    }

    private function redirectUrisMatch(string $registered, string $requested): bool
    {
        if ($registered === $requested) {
            return true;
        }

        $registeredHost = parse_url($registered, PHP_URL_HOST);
        $requestedHost = parse_url($requested, PHP_URL_HOST);

        if (! in_array($registeredHost, ['localhost', '127.0.0.1'], true)
            && ! in_array($requestedHost, ['localhost', '127.0.0.1'], true)) {
            return false;
        }

        $registeredPath = parse_url($registered, PHP_URL_PATH) ?: '';
        $requestedPath = parse_url($requested, PHP_URL_PATH) ?: '';

        return in_array($registeredHost, ['localhost', '127.0.0.1'], true)
            && $registeredHost === $requestedHost
            && $registeredPath === $requestedPath;
    }
}
