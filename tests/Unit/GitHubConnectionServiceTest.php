<?php

namespace Tests\Unit;

use App\Services\GitHubConnectionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GitHubConnectionServiceTest extends TestCase
{
    #[Test]
    public function it_normalizes_github_urls_and_git_suffix(): void
    {
        $service = app(GitHubConnectionService::class);

        $this->assertSame('Thomaspruv/maestro', $service->normalizeRepo('Thomaspruv/maestro.git'));
        $this->assertSame('Thomaspruv/maestro', $service->normalizeRepo('https://github.com/Thomaspruv/maestro'));
        $this->assertSame('Thomaspruv/maestro', $service->normalizeRepo('https://github.com/Thomaspruv/maestro.git/'));
    }

    #[Test]
    public function it_parses_normalized_repo_parts(): void
    {
        $service = app(GitHubConnectionService::class);

        $this->assertSame(['Thomaspruv', 'maestro'], $service->parseRepo('Thomaspruv/maestro.git'));
    }

    #[Test]
    public function it_uses_pat_mode_when_configured(): void
    {
        config(['maestro.github_auth' => 'pat']);

        $this->assertSame('pat', app(GitHubConnectionService::class)->authMode());
    }

    #[Test]
    public function it_uses_oauth_mode_when_configured_and_credentials_present(): void
    {
        config([
            'maestro.github_auth' => 'oauth',
            'services.github.client_id' => 'client-id',
            'services.github.client_secret' => 'client-secret',
        ]);

        $this->assertSame('oauth', app(GitHubConnectionService::class)->authMode());
    }

    #[Test]
    public function it_falls_back_to_pat_when_oauth_is_not_configured(): void
    {
        config([
            'maestro.github_auth' => 'oauth',
            'services.github.client_id' => null,
            'services.github.client_secret' => null,
        ]);

        $this->assertSame('pat', app(GitHubConnectionService::class)->authMode());
    }
}
