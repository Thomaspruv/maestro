<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\UserAgent;
use App\Services\ProjectAgentSyncService;
use Database\Seeders\UserAgentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_gets_builtin_agents_on_factory_create(): void
    {
        $user = User::factory()->create();

        $this->assertCount(9, $user->agents);
        $this->assertTrue($user->agents()->where('slug', 'pm')->first()->is_builtin);
    }

    public function test_agents_index_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('agents.index'))
            ->assertOk()
            ->assertSee('Agents');
    }

    public function test_can_create_custom_agent(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(\App\Livewire\AgentsIndex::class)
            ->set('newSlug', 'code_reviewer')
            ->set('newName', 'Code Reviewer')
            ->set('newEmoji', '🔍')
            ->set('newModel', 'claude-sonnet-4-6')
            ->set('newSystemPrompt', 'Tu es un reviewer de code expert.')
            ->call('createAgent')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_agents', [
            'user_id' => $user->id,
            'slug' => 'code_reviewer',
            'is_builtin' => false,
        ]);
    }

    public function test_cannot_delete_builtin_agent(): void
    {
        $user = User::factory()->create();
        $builtin = $user->agents()->where('slug', 'pm')->first();

        $this->assertFalse($user->can('delete', $builtin));
    }

    public function test_can_delete_custom_agent(): void
    {
        $user = User::factory()->create();

        $custom = UserAgent::factory()->create([
            'user_id' => $user->id,
            'slug' => 'custom_agent',
        ]);

        $this->assertTrue($user->can('delete', $custom));

        Livewire::actingAs($user)
            ->test(\App\Livewire\AgentsIndex::class)
            ->call('confirmDelete', $custom->id)
            ->call('deleteAgent');

        $this->assertDatabaseMissing('user_agents', ['id' => $custom->id]);
    }

    public function test_project_creation_copies_user_agents_including_custom(): void
    {
        $user = User::factory()->create();

        UserAgent::factory()->create([
            'user_id' => $user->id,
            'slug' => 'legal_reviewer',
            'name' => 'Legal Reviewer',
        ]);

        $project = Project::factory()->create(['user_id' => $user->id]);
        app(ProjectAgentSyncService::class)->copyUserAgentsToProject($user, $project);

        $this->assertCount(10, $project->agents);
        $this->assertTrue($project->agents()->where('agent_type', 'legal_reviewer')->exists());
    }
}
