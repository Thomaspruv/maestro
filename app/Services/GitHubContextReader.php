<?php

namespace App\Services;

use Github\AuthMethod;
use Github\Client;
use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Log;

class GitHubContextReader
{
    private const CONTEXT_FILES = [
        'readme' => ['README.md', 'readme.md', 'Readme.md'],
        'claude_md' => ['CLAUDE.md', 'claude.md'],
        'architecture' => ['docs/architecture.md', 'docs/ARCHITECTURE.md', 'docs/architecture/README.md'],
    ];

    /**
     * @return array<string, string|null>
     */
    public function read(string $githubRepo, string $token, ?string $branch = null): array
    {
        [$owner, $repo] = $this->parseRepo($githubRepo);
        $client = $this->client($token);
        $context = [];

        foreach (self::CONTEXT_FILES as $key => $paths) {
            $context[$key] = $this->readFirstAvailable($client, $owner, $repo, $paths, $branch);
        }

        return $context;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function readFirstAvailable(Client $client, string $owner, string $repo, array $paths, ?string $branch): ?string
    {
        foreach ($paths as $path) {
            try {
                $content = $client->repos()->contents()->show($owner, $repo, $path, $branch);

                if (! isset($content['content'])) {
                    continue;
                }

                $decoded = base64_decode((string) $content['content'], true);

                if ($decoded !== false && trim($decoded) !== '') {
                    return $decoded;
                }
            } catch (RuntimeException $e) {
                Log::debug('Fichier de contexte introuvable', ['path' => $path]);
            }
        }

        return null;
    }

    private function client(string $token): Client
    {
        $client = new Client;
        $client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);

        return $client;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseRepo(string $githubRepo): array
    {
        return app(GitHubConnectionService::class)->parseRepo($githubRepo);
    }
}
