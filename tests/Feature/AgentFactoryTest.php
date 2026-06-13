<?php

namespace Tests\Feature;

use App\Agents\AgentFactory;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\User;
use App\Models\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_prompt_from_project_agent_first(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        ProjectAgent::factory()->create([
            'project_id' => $project->id,
            'agent_type' => 'pm',
            'system_prompt' => 'Prompt projet spécifique',
        ]);

        $agent = AgentFactory::make('pm', $project);

        $this->assertSame('Prompt projet spécifique', $agent->systemPrompt());
    }

    public function test_resolves_prompt_from_user_agent_when_no_project_override(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        UserAgent::query()
            ->where('user_id', $user->id)
            ->where('slug', 'pm')
            ->update(['system_prompt' => 'Prompt compte utilisateur']);

        $agent = AgentFactory::make('pm', $project);

        $this->assertSame('Prompt compte utilisateur', $agent->systemPrompt());
    }

    public function test_custom_agent_slug_works_without_enum(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        UserAgent::factory()->create([
            'user_id' => $user->id,
            'slug' => 'custom_reviewer',
            'system_prompt' => 'Je suis un agent custom.',
        ]);

        ProjectAgent::factory()->create([
            'project_id' => $project->id,
            'agent_type' => 'custom_reviewer',
            'system_prompt' => 'Je suis un agent custom.',
        ]);

        $agent = AgentFactory::make('custom_reviewer', $project);

        $this->assertSame('Je suis un agent custom.', $agent->systemPrompt());
    }
}
