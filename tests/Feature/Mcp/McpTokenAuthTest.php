<?php

namespace Tests\Feature\Mcp;

use App\Models\McpToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McpTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->plainToken = Str::random(40);

        McpToken::create([
            'user_id' => $this->user->id,
            'name' => 'Hermes local',
            'token' => hash('sha256', $this->plainToken),
        ]);
    }

    public function test_valid_static_token_authenticates_initialize(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.$this->plainToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'maestro');
    }

    public function test_token_updates_last_used_at(): void
    {
        $token = McpToken::firstOrFail();
        $this->assertNull($token->last_used_at);

        $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.$this->plainToken,
        ])->assertOk();

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_token_with_surrounding_whitespace_is_accepted(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer  '.$this->plainToken.'  ',
        ]);

        $response->assertOk();
    }

    public function test_token_pasted_with_bearer_prefix_in_value_is_accepted(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer Bearer '.$this->plainToken,
        ]);

        $response->assertOk();
    }

    public function test_wrong_token_returns_401(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.Str::random(40),
        ]);

        $response->assertUnauthorized();
    }

    public function test_revoked_token_returns_401(): void
    {
        McpToken::query()->delete();

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.$this->plainToken,
        ]);

        $response->assertUnauthorized();
    }
}
