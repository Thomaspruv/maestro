<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\GitHubConnectionService;
use App\Services\GitHubTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class GitHubTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_repository_from_template(): void
    {
        Config::set('maestro.github_template_repo', 'acme/maestro-template');

        $this->mock(GitHubConnectionService::class, function ($mock): void {
            $mock->shouldReceive('resolveToken')->andReturn('ghp_test_token_1234567890');
            $mock->shouldReceive('parseRepo')->with('acme/maestro-template')->andReturn(['acme', 'maestro-template']);
            $mock->shouldReceive('fetchProfile')->andReturn(['login' => 'thomas', 'name' => 'Thomas']);
        });

        Http::fake([
            'https://api.github.com/repos/acme/maestro-template/generate' => Http::response([
                'full_name' => 'thomas/my-new-app',
                'html_url' => 'https://github.com/thomas/my-new-app',
                'default_branch' => 'main',
            ], 201),
        ]);

        $user = User::factory()->create([
            'github_token' => 'ghp_test_token_1234567890',
            'github_username' => 'thomas',
        ]);

        $result = app(GitHubTemplateService::class)->createFromTemplate(
            $user,
            'my-new-app',
            'Mon projet Maestro',
            'private',
        );

        $this->assertSame('thomas/my-new-app', $result['full_name']);
        $this->assertSame('https://github.com/thomas/my-new-app', $result['html_url']);
        $this->assertSame('main', $result['default_branch']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.github.com/repos/acme/maestro-template/generate'
                && $request['name'] === 'my-new-app'
                && $request['owner'] === 'thomas'
                && $request['private'] === true;
        });
    }

    #[Test]
    public function it_fails_when_template_config_is_missing(): void
    {
        Config::set('maestro.github_template_repo', '');

        $user = User::factory()->create([
            'github_token' => 'ghp_test_token_1234567890',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template GitHub non configuré');

        app(GitHubTemplateService::class)->createFromTemplate($user, 'my-app', 'Desc');
    }

    #[Test]
    public function it_fails_when_template_is_not_found(): void
    {
        Config::set('maestro.github_template_repo', 'acme/missing-template');

        $this->mock(GitHubConnectionService::class, function ($mock): void {
            $mock->shouldReceive('resolveToken')->andReturn('ghp_test_token_1234567890');
            $mock->shouldReceive('parseRepo')->with('acme/missing-template')->andReturn(['acme', 'missing-template']);
            $mock->shouldReceive('fetchProfile')->andReturn(['login' => 'thomas']);
        });

        Http::fake([
            'https://api.github.com/repos/acme/missing-template/generate' => Http::response([
                'message' => 'Not Found',
            ], 404),
        ]);

        $user = User::factory()->create([
            'github_token' => 'ghp_test_token_1234567890',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template GitHub introuvable');

        app(GitHubTemplateService::class)->createFromTemplate($user, 'my-app', 'Desc');
    }

    #[Test]
    public function it_fails_when_repository_name_already_exists(): void
    {
        Config::set('maestro.github_template_repo', 'acme/maestro-template');

        $this->mock(GitHubConnectionService::class, function ($mock): void {
            $mock->shouldReceive('resolveToken')->andReturn('ghp_test_token_1234567890');
            $mock->shouldReceive('parseRepo')->with('acme/maestro-template')->andReturn(['acme', 'maestro-template']);
            $mock->shouldReceive('fetchProfile')->andReturn(['login' => 'thomas']);
        });

        Http::fake([
            'https://api.github.com/repos/acme/maestro-template/generate' => Http::response([
                'message' => 'Repository creation failed.',
                'errors' => [['message' => 'name already exists on this account']],
            ], 422),
        ]);

        $user = User::factory()->create([
            'github_token' => 'ghp_test_token_1234567890',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('existe déjà');

        app(GitHubTemplateService::class)->createFromTemplate($user, 'my-app', 'Desc');
    }
}
