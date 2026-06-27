<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\User;
use App\Services\AnthropicClient;
use App\Services\DiscoveryChatService;
use App\Services\UrlCrawlerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DiscoveryChatHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_trims_history_to_configured_max(): void
    {
        config(['maestro.discovery_max_history' => 10]);

        $user = User::factory()->create(['claude_api_key' => 'test-key']);
        $project = Project::factory()->create(['user_id' => $user->id]);

        $history = [];
        for ($i = 1; $i <= 15; $i++) {
            $history[] = ['role' => 'user', 'content' => "message-{$i}"];
            $history[] = ['role' => 'assistant', 'content' => "reply-{$i}"];
        }

        $mockAnthropic = Mockery::mock(AnthropicClient::class);
        $mockAnthropic->shouldReceive('createConversation')
            ->once()
            ->withArgs(function (
                string $apiKey,
                string $model,
                array $systemBlocks,
                array $messages,
            ) {
                $this->assertCount(11, $messages);
                $this->assertSame('message-11', $messages[0]['content']);
                $this->assertSame('reply-15', $messages[9]['content']);
                $this->assertSame('user', $messages[10]['role']);

                return true;
            })
            ->andReturn([
                'text' => 'ok',
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                    'cache_read_input_tokens' => 0,
                ],
            ]);

        $this->app->instance(AnthropicClient::class, $mockAnthropic);

        $mockCrawler = Mockery::mock(UrlCrawlerService::class);
        $mockCrawler->shouldReceive('extractUrls')->andReturn([]);
        $this->app->instance(UrlCrawlerService::class, $mockCrawler);

        app(DiscoveryChatService::class)->send($project, $user, $history, 'nouveau message');
    }
}
