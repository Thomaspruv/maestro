<?php

namespace Tests\Feature;

use App\Enums\TaskType;
use App\Livewire\DiscoveryChat;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AnthropicClient;
use App\Services\DiscoveryChatService;
use App\Services\UrlCrawlerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class DiscoveryChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_page_loads(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('projects.discovery', $project))
            ->assertOk()
            ->assertSee('Discovery IA');
    }

    public function test_parse_response_extracts_tasks(): void
    {
        $text = <<<'TEXT'
Voici mes recommandations basées sur votre backlog.

<tasks>
[
  {
    "title": "Ajouter un filtre Kanban",
    "description": "Permettre de filtrer par module",
    "type": "feature",
    "priority": "high",
    "module": "Tasks"
  }
]
</tasks>
TEXT;

        $parsed = DiscoveryChatService::parseResponse($text);

        $this->assertStringNotContainsString('<tasks>', $parsed['display_text']);
        $this->assertCount(1, $parsed['proposed_tasks']);
        $this->assertSame('Ajouter un filtre Kanban', $parsed['proposed_tasks'][0]['title']);
        $this->assertSame('feature', $parsed['proposed_tasks'][0]['type']);
    }

    public function test_send_adds_messages_with_proposed_tasks(): void
    {
        $user = User::factory()->create(['claude_api_key' => 'test-key']);
        $project = Project::factory()->create(['user_id' => $user->id]);

        $mockAnthropic = Mockery::mock(AnthropicClient::class);
        $mockAnthropic->shouldReceive('createConversation')
            ->once()
            ->andReturn([
                'text' => "Analyse terminée.\n\n<tasks>[{\"title\":\"Nouvelle feature\",\"description\":\"Desc\",\"type\":\"feature\",\"priority\":\"medium\",\"module\":null}]</tasks>",
                'usage' => [
                    'input_tokens' => 100,
                    'output_tokens' => 50,
                    'cache_read_input_tokens' => 0,
                ],
            ]);

        $this->app->instance(AnthropicClient::class, $mockAnthropic);

        $mockCrawler = Mockery::mock(UrlCrawlerService::class);
        $mockCrawler->shouldReceive('extractUrls')->andReturn([]);
        $this->app->instance(UrlCrawlerService::class, $mockCrawler);

        Livewire::actingAs($user)
            ->test(DiscoveryChat::class, ['project' => $project])
            ->set('message', 'Analyse mon repo')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('messages.1.role', 'assistant')
            ->tap(function ($component) {
                $messages = $component->get('messages');
                $this->assertCount(2, $messages);
                $this->assertCount(1, $messages[1]['proposed_tasks']);
                $this->assertSame('Nouvelle feature', $messages[1]['proposed_tasks'][0]['title']);
            });
    }

    public function test_add_task_creates_task_in_backlog(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(DiscoveryChat::class, ['project' => $project])
            ->set('messages', [
                [
                    'role' => 'assistant',
                    'content' => 'Voici une tâche.',
                    'proposed_tasks' => [
                        [
                            'title' => 'Tâche discovery',
                            'description' => 'Description test',
                            'type' => 'feature',
                            'priority' => 'high',
                            'module' => 'Tasks',
                            'status' => 'pending',
                        ],
                    ],
                ],
            ])
            ->call('addTask', 0, 0);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Tâche discovery',
        ]);

        $task = Task::query()->where('title', 'Tâche discovery')->first();
        $this->assertSame(TaskType::Feature, $task->type);
    }

    public function test_dismiss_task_marks_as_dismissed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(DiscoveryChat::class, ['project' => $project])
            ->set('messages', [
                [
                    'role' => 'assistant',
                    'content' => 'Voici une tâche.',
                    'proposed_tasks' => [
                        [
                            'title' => 'Tâche à ignorer',
                            'description' => 'Desc',
                            'type' => 'chore',
                            'priority' => 'low',
                            'module' => null,
                            'status' => 'pending',
                        ],
                    ],
                ],
            ])
            ->call('dismissTask', 0, 0)
            ->assertSet('messages.0.proposed_tasks.0.status', 'dismissed');

        $this->assertDatabaseMissing('tasks', ['title' => 'Tâche à ignorer']);
    }

    public function test_launch_discovery_runs_full_analysis(): void
    {
        $user = User::factory()->create(['claude_api_key' => 'test-key']);
        $project = Project::factory()->create(['user_id' => $user->id]);

        $mockAnthropic = Mockery::mock(AnthropicClient::class);
        $mockAnthropic->shouldReceive('createConversation')
            ->once()
            ->andReturn([
                'text' => "Discovery complète.\n\n<tasks>[{\"title\":\"Feature veille\",\"description\":\"Desc\",\"type\":\"feature\",\"priority\":\"high\",\"module\":null}]</tasks>",
                'usage' => [
                    'input_tokens' => 200,
                    'output_tokens' => 80,
                    'cache_read_input_tokens' => 0,
                ],
            ]);

        $this->app->instance(AnthropicClient::class, $mockAnthropic);

        $mockCrawler = Mockery::mock(UrlCrawlerService::class);
        $mockCrawler->shouldReceive('extractUrls')
            ->andReturnUsing(fn (string $text) => preg_match_all('#https?://[^\s<>"\'\)\]]+#i', $text, $m) ? array_values(array_unique($m[0])) : []);
        $mockCrawler->shouldReceive('fetch')->atLeast()->once()->andReturn('<html>Veille marché</html>');
        $this->app->instance(UrlCrawlerService::class, $mockCrawler);

        Livewire::actingAs($user)
            ->test(DiscoveryChat::class, ['project' => $project])
            ->call('launchDiscovery')
            ->assertHasNoErrors()
            ->assertSet('messages.0.content', 'Lancer la Discovery')
            ->assertSet('messages.1.role', 'assistant')
            ->tap(function ($component) {
                $messages = $component->get('messages');
                $this->assertCount(2, $messages);
                $this->assertCount(1, $messages[1]['proposed_tasks']);
            });
    }

    public function test_user_has_discovery_agent_after_factory_create(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(
            $user->agents()->where('slug', 'discovery')->exists()
        );
    }
}
