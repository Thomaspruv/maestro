<?php

namespace Tests\Feature;

use App\Pipeline\RunnerFactory;
use App\Models\Project;
use App\Models\ProjectRole;
use App\Models\User;
use App\Models\PipelineRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunnerFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_prompt_from_project_agent_first(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        ProjectRole::factory()->create([
            'project_id' => $project->id,
            'role' => 'pm',
            'system_prompt' => 'Prompt projet spécifique',
        ]);

        $agent = RunnerFactory::make('pm', $project);

        $this->assertSame('Prompt projet spécifique', $agent->systemPrompt());
    }

    public function test_resolves_prompt_from_user_agent_when_no_project_override(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        PipelineRole::query()
            ->where('user_id', $user->id)
            ->where('slug', 'pm')
            ->update(['system_prompt' => 'Prompt compte utilisateur']);

        $agent = RunnerFactory::make('pm', $project);

        $this->assertSame('Prompt compte utilisateur', $agent->systemPrompt());
    }

    public function test_custom_agent_slug_works_without_enum(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        PipelineRole::factory()->create([
            'user_id' => $user->id,
            'slug' => 'custom_reviewer',
            'system_prompt' => 'Je suis un agent custom.',
        ]);

        ProjectRole::factory()->create([
            'project_id' => $project->id,
            'role' => 'custom_reviewer',
            'system_prompt' => 'Je suis un agent custom.',
        ]);

        $agent = RunnerFactory::make('custom_reviewer', $project);

        $this->assertSame('Je suis un agent custom.', $agent->systemPrompt());
    }
}
