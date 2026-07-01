<?php

namespace Tests\Feature\Mcp;

use App\Models\McpOAuthAccessToken;
use App\Models\McpOAuthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_mcp_returns_401_with_www_authenticate(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ]);

        $response->assertUnauthorized();
        $this->assertStringContainsString(
            'resource_metadata=',
            (string) $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_protected_resource_metadata_is_exposed(): void
    {
        $response = $this->getJson('/.well-known/oauth-protected-resource');

        $response->assertOk()
            ->assertJsonPath('resource', rtrim(config('app.url'), '/').'/api/mcp')
            ->assertJsonStructure(['authorization_servers', 'scopes_supported']);
    }

    public function test_authorization_server_metadata_is_exposed(): void
    {
        $response = $this->getJson('/.well-known/oauth-authorization-server');

        $response->assertOk()
            ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token'])
            ->assertJsonPath('code_challenge_methods_supported', ['S256']);
    }

    public function test_dynamic_client_registration_creates_client(): void
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Claude Cowork',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['client_id', 'redirect_uris']);

        $this->assertDatabaseHas('mcp_oauth_clients', [
            'client_id' => $response->json('client_id'),
        ]);
    }

    public function test_oauth_flow_issues_token_usable_on_mcp(): void
    {
        $client = McpOAuthClient::create([
            'client_id' => 'test-client-id',
            'client_name' => 'Test',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ]);

        $codeVerifier = Str::random(64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $authorizeResponse = $this->actingAs($this->user)->post('/oauth/authorize', [
            'client_id' => $client->client_id,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
        ]);

        $authorizeResponse->assertRedirect();
        $redirectUrl = $authorizeResponse->headers->get('Location');
        $this->assertNotNull($redirectUrl);
        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('code', $query);

        $tokenResponse = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $query['code'],
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'client_id' => $client->client_id,
            'code_verifier' => $codeVerifier,
        ]);

        $tokenResponse->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in']);

        $accessToken = $tokenResponse->json('access_token');

        $mcpResponse = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.$accessToken,
        ]);

        $mcpResponse->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'maestro');
    }

    public function test_refresh_token_rotates_and_works_on_mcp(): void
    {
        $client = McpOAuthClient::create([
            'client_id' => 'refresh-client',
            'client_name' => 'Test',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ]);

        $refreshPlain = Str::random(64);
        $accessPlain = Str::random(64);

        McpOAuthAccessToken::create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'access_token' => hash('sha256', $accessPlain),
            'refresh_token' => hash('sha256', $refreshPlain),
            'scope' => 'mcp:read mcp:write',
            'expires_at' => now()->subMinute(),
            'refresh_expires_at' => now()->addDay(),
        ]);

        $tokenResponse = $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshPlain,
            'client_id' => $client->client_id,
        ]);

        $tokenResponse->assertOk();
        $newAccess = $tokenResponse->json('access_token');

        $mcpResponse = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.$newAccess,
        ]);

        $mcpResponse->assertOk();
        $this->assertCount(13, $mcpResponse->json('result.tools'));
    }
}
