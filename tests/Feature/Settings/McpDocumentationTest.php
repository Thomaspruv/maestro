<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_docs_page_is_public(): void
    {
        $this->get(route('settings.mcp.docs'))
            ->assertOk()
            ->assertSee('/api/mcp', false)
            ->assertSee('list_hermes_tasks', false)
            ->assertSee('record_step_output', false)
            ->assertSee('Référence des tools', false)
            ->assertSee('hermes_only', false)
            ->assertSee('Page publique', false);
    }

    public function test_mcp_docs_page_works_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.mcp.docs'))
            ->assertOk()
            ->assertSee('Intégrations MCP', false);
    }
}
