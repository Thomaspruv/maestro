<?php

namespace App\Livewire;

use App\Services\GitHubConnectionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GitHubConnect extends Component
{
    public bool $compact = false;

    public string $redirect = '';

    public string $github_token = '';

    public function mount(bool $compact = false, ?string $redirect = null): void
    {
        $this->compact = $compact;
        $this->redirect = $redirect ?? url()->current();
    }

    public function connect(GitHubConnectionService $github): void
    {
        session()->forget('github_connect_success');

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
        session()->flash('github_connect_success', 'Compte GitHub connecté ('.$profile['login'].').');

        $this->dispatch('github-connected');
    }

    public function disconnect(GitHubConnectionService $github): void
    {
        $github->disconnectUser(Auth::user());
        $this->github_token = '';
        session()->forget('github_connect_success');

        $this->dispatch('github-disconnected');
    }

    public function render(GitHubConnectionService $github)
    {
        $user = Auth::user();

        return view('livewire.github-connect', [
            'authMode' => $github->authMode(),
            'tokenUrl' => $github->personalAccessTokenUrl(),
            'oauthConfigured' => $github->isOAuthConfigured(),
            'connectUrl' => route('github.redirect', ['redirect' => $this->redirect]),
            'compact' => $this->compact,
            'connected' => $user?->hasGithubConnection() ?? false,
            'username' => $user?->github_username,
            'successMessage' => session('github_connect_success'),
        ]);
    }
}
