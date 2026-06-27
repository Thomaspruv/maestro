<?php

namespace App\Livewire;

use App\Services\GitHubConnectionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GitHubConnect extends Component
{
    public bool $compact = false;

    public string $redirect = '';

    public string $github_token = '';

    public bool $connected = false;

    #[Locked]
    public ?string $username = null;

    public ?string $successMessage = null;

    public function mount(bool $compact = false, ?string $redirect = null): void
    {
        $this->compact = $compact;
        $this->redirect = $redirect ?? url()->current();
        $this->syncFromUser();
    }

    public function connect(GitHubConnectionService $github): void
    {
        $this->reset('successMessage');
        $this->validate([
            'github_token' => ['required', 'string', 'min:20'],
        ], [
            'github_token.required' => 'Collez le token généré sur GitHub.',
            'github_token.min' => 'Le token GitHub semble trop court.',
        ]);

        try {
            $profile = $github->connectUserWithToken(Auth::user(), trim($this->github_token));
        } catch (\Throwable) {
            $this->addError('github_token', 'Token GitHub invalide ou expiré.');

            return;
        }

        $this->github_token = '';
        $this->syncFromUser();
        $this->successMessage = 'Compte GitHub connecté ('.$profile['login'].').';

        $this->dispatch('github-connected');
    }

    public function disconnect(GitHubConnectionService $github): void
    {
        $github->disconnectUser(Auth::user());
        $this->reset('github_token', 'successMessage');
        $this->syncFromUser();

        $this->dispatch('github-disconnected');
    }

    public function render(GitHubConnectionService $github)
    {
        return view('livewire.github-connect', [
            'authMode' => $github->authMode(),
            'tokenUrl' => $github->personalAccessTokenUrl(),
            'oauthConfigured' => $github->isOAuthConfigured(),
            'connectUrl' => route('github.redirect', ['redirect' => $this->redirect]),
        ]);
    }

    private function syncFromUser(): void
    {
        $user = Auth::user();
        $user->refresh();

        $this->connected = $user->hasGithubConnection();
        $this->username = $user->github_username;
    }
}
