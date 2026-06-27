<?php

namespace App\Services\Mcp;

use App\Models\McpOAuthAccessToken;
use App\Models\McpOAuthAuthorizationCode;
use App\Models\McpOAuthClient;
use App\Models\User;
use Illuminate\Support\Str;

class McpOAuthService
{
    public function issuer(): string
    {
        return rtrim((string) config('maestro.mcp.oauth.issuer', config('app.url')), '/');
    }

    public function resourceUrl(): string
    {
        return rtrim((string) config('maestro.mcp.resource_url'), '/');
    }

    public function protectedResourceMetadataUrl(): string
    {
        return $this->issuer().'/.well-known/oauth-protected-resource';
    }

    /**
     * @return array<string, mixed>
     */
    public function protectedResourceMetadata(): array
    {
        return [
            'resource' => $this->resourceUrl(),
            'authorization_servers' => [$this->issuer()],
            'scopes_supported' => config('maestro.mcp.oauth.scopes'),
            'bearer_methods_supported' => ['header'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function authorizationServerMetadata(): array
    {
        $issuer = $this->issuer();

        return [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'registration_endpoint' => $issuer.'/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'client_id_metadata_document_supported' => true,
            'scopes_supported' => config('maestro.mcp.oauth.scopes'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function registerClient(array $payload): array
    {
        $redirectUris = $payload['redirect_uris'] ?? [];

        if (! is_array($redirectUris) || $redirectUris === []) {
            throw new McpOAuthException('invalid_redirect_uri', 'redirect_uris is required');
        }

        $clientId = Str::random(32);

        $client = McpOAuthClient::create([
            'client_id' => $clientId,
            'client_name' => is_string($payload['client_name'] ?? null) ? $payload['client_name'] : null,
            'redirect_uris' => array_values($redirectUris),
        ]);

        return [
            'client_id' => $client->client_id,
            'client_id_issued_at' => $client->created_at?->getTimestamp() ?? time(),
            'redirect_uris' => $client->redirect_uris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ];
    }

    public function findClient(string $clientId): ?McpOAuthClient
    {
        return McpOAuthClient::query()->where('client_id', $clientId)->first();
    }

    /**
     * @return array{plain: string, model: McpOAuthAuthorizationCode}
     */
    public function issueAuthorizationCode(
        McpOAuthClient $client,
        User $user,
        string $redirectUri,
        string $codeChallenge,
        string $codeChallengeMethod,
        ?string $scope,
    ): array {
        $plain = Str::random(64);

        $model = McpOAuthAuthorizationCode::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'code' => hash('sha256', $plain),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'expires_at' => now()->addMinutes(10),
        ]);

        return ['plain' => $plain, 'model' => $model];
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(
        string $code,
        string $clientId,
        string $redirectUri,
        string $codeVerifier,
    ): array {
        $client = $this->findClient($clientId);

        if ($client === null) {
            throw new McpOAuthException('invalid_client', 'Unknown client');
        }

        if (! $client->allowsRedirectUri($redirectUri)) {
            throw new McpOAuthException('invalid_grant', 'Invalid redirect_uri');
        }

        $authCode = McpOAuthAuthorizationCode::query()
            ->where('client_id', $client->id)
            ->where('code', hash('sha256', $code))
            ->first();

        if ($authCode === null || ! $authCode->isValid()) {
            throw new McpOAuthException('invalid_grant', 'Invalid or expired authorization code');
        }

        if ($authCode->redirect_uri !== $redirectUri) {
            throw new McpOAuthException('invalid_grant', 'redirect_uri mismatch');
        }

        if (! $this->verifyPkce($codeVerifier, $authCode->code_challenge, $authCode->code_challenge_method)) {
            throw new McpOAuthException('invalid_grant', 'PKCE verification failed');
        }

        $authCode->forceFill(['used_at' => now()])->save();

        return $this->issueTokens($client, $authCode->user, $authCode->scope);
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshAccessToken(string $refreshToken, string $clientId): array
    {
        $client = $this->findClient($clientId);

        if ($client === null) {
            throw new McpOAuthException('invalid_client', 'Unknown client');
        }

        $token = McpOAuthAccessToken::query()
            ->where('client_id', $client->id)
            ->where('refresh_token', hash('sha256', $refreshToken))
            ->with('user')
            ->first();

        if ($token === null || ! $token->isRefreshTokenValid() || $token->user === null) {
            throw new McpOAuthException('invalid_grant', 'Invalid or expired refresh token');
        }

        $token->delete();

        return $this->issueTokens($client, $token->user, $token->scope);
    }

    public function findUserByAccessToken(string $plainToken): ?User
    {
        $token = McpOAuthAccessToken::query()
            ->where('access_token', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();

        if ($token === null || $token->user === null) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $token->user;
    }

    /**
     * @return array<string, mixed>
     */
    private function issueTokens(McpOAuthClient $client, User $user, ?string $scope): array
    {
        $accessPlain = Str::random(64);
        $refreshPlain = Str::random(64);
        $accessTtl = (int) config('maestro.mcp.oauth.access_token_ttl', 3600);
        $refreshTtl = (int) config('maestro.mcp.oauth.refresh_token_ttl', 2592000);

        McpOAuthAccessToken::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'access_token' => hash('sha256', $accessPlain),
            'refresh_token' => hash('sha256', $refreshPlain),
            'scope' => $scope,
            'expires_at' => now()->addSeconds($accessTtl),
            'refresh_expires_at' => now()->addSeconds($refreshTtl),
        ]);

        return [
            'access_token' => $accessPlain,
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl,
            'refresh_token' => $refreshPlain,
            'scope' => $scope ?? implode(' ', config('maestro.mcp.oauth.scopes', [])),
        ];
    }

    private function verifyPkce(string $verifier, string $challenge, string $method): bool
    {
        if ($method !== 'S256') {
            return false;
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }
}
