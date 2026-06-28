<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_docs_page_requires_auth(): void
    {
        $this->get(route('settings.mcp.docs'))
            ->assertRedirect();
    }

    public function test_mcp_docs_page_shows_endpoint_and_tools(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('settings.mcp.docs'));

        $response->assertOk()
            ->assertSee('/api/mcp', false)
            ->assertSee('list_hermes_tasks', false)
            ->assertSee('record_step_output', false)
            ->assertSee('Référence des tools', false)
            ->assertSee('hermes_only', false);
    }
}
