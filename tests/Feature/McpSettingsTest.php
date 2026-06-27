<?php

namespace Tests\Feature;

use App\Models\McpToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McpSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_settings_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings/mcp')
            ->assertOk()
            ->assertSee('Intégrations MCP')
            ->assertSee('/api/mcp')
            ->assertSee(url('/api/mcp'));
    }

    public function test_user_can_generate_mcp_token_from_ui(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/mcp-tokens', [
            'name' => 'Hermes prod',
        ]);

        $response->assertRedirect(route('settings.mcp'));
        $response->assertSessionHas('mcp_token_plain');

        $this->assertDatabaseHas('mcp_tokens', [
            'user_id' => $user->id,
            'name' => 'Hermes prod',
        ]);
    }

    public function test_user_can_revoke_own_mcp_token(): void
    {
        $user = User::factory()->create();
        $plain = Str::random(40);

        $token = McpToken::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'token' => hash('sha256', $plain),
        ]);

        $this->actingAs($user)
            ->delete(route('settings.mcp-tokens.destroy', $token))
            ->assertRedirect(route('settings.mcp'));

        $this->assertDatabaseMissing('mcp_tokens', ['id' => $token->id]);
    }
}
