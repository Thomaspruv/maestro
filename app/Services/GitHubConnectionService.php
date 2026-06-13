<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Github\AuthMethod;
use Github\Client;
use Github\Exception\RuntimeException;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class GitHubConnectionService
{
    /**
     * @return array{0: string, 1: string}
     */
    public function parseRepo(string $input): array
    {
        $normalized = $this->normalizeRepo($input);
        $parts = explode('/', $normalized, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException("Format de dépôt GitHub invalide : {$input}");
        }

        return [$parts[0], $parts[1]];
    }

    public function normalizeRepo(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            return '';
        }

        if (preg_match('~github\.com[:/]+([^/\s?#]+)/([^/\s?#]+)~i', $input, $matches)) {
            $input = $matches[1].'/'.rtrim($matches[2], '/');
        }

        $input = rtrim($input, '/');

        if (str_ends_with(strtolower($input), '.git')) {
            $input = substr($input, 0, -4);
        }

        if (! preg_match('/^[\w.\-]+\/[\w.\-]+$/', $input)) {
            throw new InvalidArgumentException(
                'Utilisez le format owner/repo (ex. mon-org/mon-projet) ou une URL GitHub complète.'
            );
        }

        return $input;
    }

    public function resolveToken(User $user, ?Project $project = null): ?string
    {
        if ($project?->github_token) {
            return $project->github_token;
        }

        return $user->github_token;
    }

    public function isConnected(User $user): bool
    {
        return filled($user->github_token);
    }

    public function isOAuthConfigured(): bool
    {
        return filled(config('services.github.client_id'))
            && filled(config('services.github.client_secret'));
    }

    public function authMode(): string
    {
        $mode = (string) config('maestro.github_auth', 'auto');

        if ($mode === 'pat') {
            return 'pat';
        }

        if ($mode === 'oauth') {
            return $this->isOAuthConfigured() ? 'oauth' : 'pat';
        }

        if (app()->environment('local') && ! config('maestro.github_oauth_in_local', false)) {
            return 'pat';
        }

        return $this->isOAuthConfigured() ? 'oauth' : 'pat';
    }

    public function usesPersonalAccessTokenFlow(): bool
    {
        return $this->authMode() === 'pat';
    }

    /**
     * @return array{login: string, name: ?string, avatar_url: ?string}
     */
    public function connectUserWithToken(User $user, string $token): array
    {
        $profile = $this->fetchProfile($token);

        $user->update([
            'github_token' => $token,
            'github_username' => $profile['login'],
            'github_connected_at' => now(),
        ]);

        session(['github_oauth_token' => $token]);

        return $profile;
    }

    public function disconnectUser(User $user): void
    {
        $user->update([
            'github_token' => null,
            'github_username' => null,
            'github_connected_at' => null,
        ]);

        session()->forget('github_oauth_token');
    }

    public function personalAccessTokenUrl(): string
    {
        return 'https://github.com/settings/tokens/new?scopes=repo,read:user&description='.urlencode(config('app.name', 'Maestro'));
    }

    /**
     * @return array{login: string, name: ?string, avatar_url: ?string}
     */
    public function fetchProfile(string $token): array
    {
        $client = $this->client($token);
        $profile = $client->currentUser()->show();

        return [
            'login' => (string) ($profile['login'] ?? ''),
            'name' => $profile['name'] ?? null,
            'avatar_url' => $profile['avatar_url'] ?? null,
        ];
    }

    /**
     * @return array<int, array{full_name: string, name: string, private: bool, default_branch: string, html_url: string}>
     */
    public function listRepositories(User $user): array
    {
        $token = $user->github_token;

        if (! $token) {
            return [];
        }

        try {
            $client = $this->client($token);
            $login = $this->fetchProfile($token)['login'];
            $repos = $client->api('me')->repos()->all($login, 'owner', 'updated', 'desc', 1, 100);

            return collect($repos)
                ->map(fn (array $repo) => [
                    'full_name' => (string) ($repo['full_name'] ?? ''),
                    'name' => (string) ($repo['name'] ?? ''),
                    'private' => (bool) ($repo['private'] ?? false),
                    'default_branch' => (string) ($repo['default_branch'] ?? 'main'),
                    'html_url' => (string) ($repo['html_url'] ?? ''),
                ])
                ->filter(fn (array $repo) => $repo['full_name'] !== '')
                ->values()
                ->all();
        } catch (RuntimeException $e) {
            Log::warning('GitHub listRepositories failed', ['error' => $e->getMessage()]);

            throw new InvalidArgumentException('Impossible de lister vos dépôts GitHub. Reconnectez votre compte.');
        }
    }

    public function connectWithOAuthCode(string $code): array
    {
        if (! $this->isOAuthConfigured()) {
            throw new InvalidArgumentException('OAuth GitHub non configuré sur cette instance.');
        }

        $http = new HttpClient(['timeout' => 15]);
        $response = $http->post('https://github.com/login/oauth/access_token', [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'client_id' => config('services.github.client_id'),
                'client_secret' => config('services.github.client_secret'),
                'code' => $code,
                'redirect_uri' => route('github.callback'),
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $token = $data['access_token'] ?? null;

        if (! $token) {
            throw new InvalidArgumentException('Token GitHub manquant dans la réponse OAuth.');
        }

        $profile = $this->fetchProfile($token);

        return [
            'token' => $token,
            'username' => $profile['login'],
        ];
    }

    public function repoCloneUrl(Project $project, User $user): string
    {
        $token = $this->resolveToken($user, $project);

        if (! $token) {
            throw new InvalidArgumentException(
                'Aucun accès GitHub configuré. Connectez votre compte dans Paramètres ou configurez le dépôt du projet.'
            );
        }

        [$owner, $repo] = $this->parseRepo($project->github_repo);

        return "https://{$token}@github.com/{$owner}/{$repo}.git";
    }

    private function client(string $token): Client
    {
        $client = new Client;
        $client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);

        return $client;
    }
}
