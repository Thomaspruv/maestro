<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubTemplateService
{
    public function __construct(
        private readonly GitHubConnectionService $github,
    ) {}

    /**
     * @return array{full_name: string, html_url: string, default_branch: string}
     */
    public function createFromTemplate(
        User $user,
        string $newRepoName,
        string $description,
        string $visibility = 'private',
    ): array {
        $templateRepo = trim((string) config('maestro.github_template_repo'));

        if ($templateRepo === '') {
            throw new RuntimeException(
                'Template GitHub non configuré. Définissez MAESTRO_GITHUB_TEMPLATE_REPO dans .env.'
            );
        }

        $token = $this->github->resolveToken($user);

        if (! filled($token)) {
            throw new RuntimeException(
                'Token GitHub manquant. Connectez votre compte dans Paramètres → GitHub.'
            );
        }

        [$templateOwner, $templateName] = $this->github->parseRepo($templateRepo);
        $profile = $this->github->fetchProfile($token);
        $owner = $profile['login'];

        if ($owner === '') {
            throw new RuntimeException('Impossible de déterminer le compte GitHub connecté.');
        }

        $newRepoName = trim($newRepoName);
        $isPrivate = $visibility !== 'public';

        try {
            $response = Http::withToken($token)
                ->accept('application/vnd.github+json')
                ->timeout(30)
                ->post("https://api.github.com/repos/{$templateOwner}/{$templateName}/generate", [
                    'owner' => $owner,
                    'name' => $newRepoName,
                    'description' => $description,
                    'private' => $isPrivate,
                ])
                ->throw();
        } catch (RequestException $e) {
            throw $this->mapException($e);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        $fullName = (string) ($data['full_name'] ?? "{$owner}/{$newRepoName}");
        $htmlUrl = (string) ($data['html_url'] ?? "https://github.com/{$fullName}");

        return [
            'full_name' => $fullName,
            'html_url' => $htmlUrl,
            'default_branch' => (string) ($data['default_branch'] ?? 'main'),
        ];
    }

    private function mapException(RequestException $e): RuntimeException
    {
        $status = $e->response?->status();
        $message = (string) ($e->response?->json('message') ?? $e->getMessage());

        if ($status === 404) {
            return new RuntimeException(
                'Template GitHub introuvable. Vérifiez MAESTRO_GITHUB_TEMPLATE_REPO et les droits d\'accès.'
            );
        }

        if ($status === 422) {
            $errors = $e->response?->json('errors') ?? [];
            $errorsText = collect($errors)->pluck('message')->implode(' ');

            if (str_contains(strtolower($message.' '.$errorsText), 'already exists')) {
                return new RuntimeException('Un dépôt avec ce nom existe déjà sur votre compte GitHub.');
            }

            return new RuntimeException("Création du dépôt refusée par GitHub : {$message}");
        }

        return new RuntimeException("Échec de la création du dépôt depuis le template : {$message}");
    }
}
