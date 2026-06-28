<?php

namespace Tests\Support;

use App\Models\McpToken;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait McpTestHelpers
{
    protected User $mcpUser;

    protected string $mcpPlainToken;

    protected function setUpMcpAuth(?User $user = null): void
    {
        $this->mcpUser = $user ?? User::factory()->create();
        $this->mcpPlainToken = Str::random(40);

        McpToken::create([
            'user_id' => $this->mcpUser->id,
            'name' => 'Test MCP',
            'token' => hash('sha256', $this->mcpPlainToken),
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function mcp(string $method, array $params = [], ?string $token = null): TestResponse
    {
        return $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], [
            'Authorization' => 'Bearer '.($token ?? $this->mcpPlainToken),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mcpToolResult(TestResponse $response): array
    {
        return json_decode($response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR);
    }
}
