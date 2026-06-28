<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McpTestHelpers;
use Tests\TestCase;

class McpProtocolTest extends TestCase
{
    use McpTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcpAuth();
    }

    public function test_invalid_jsonrpc_returns_400(): void
    {
        $response = $this->postJson('/api/mcp', [
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.$this->mcpPlainToken,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', -32600);
    }

    public function test_unknown_method_returns_404(): void
    {
        $response = $this->mcp('ping');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', -32601);
    }

    public function test_notification_without_id_returns_204(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.$this->mcpPlainToken,
        ]);

        $response->assertNoContent();
    }

    public function test_unknown_tool_returns_422(): void
    {
        $response = $this->mcp('tools/call', [
            'name' => 'nonexistent_tool',
            'arguments' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', -32602);
    }

    public function test_tool_missing_required_param_returns_422(): void
    {
        $response = $this->mcp('tools/call', [
            'name' => 'get_task',
            'arguments' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', -32602)
            ->assertJsonPath('error.message', 'Paramètre requis manquant : task_id');
    }
}
