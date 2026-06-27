<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_viewer_requires_authentication(): void
    {
        $this->get('/log-viewer')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_log_viewer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/log-viewer')
            ->assertOk();
    }
}
