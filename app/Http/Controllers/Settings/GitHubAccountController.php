<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateGitHubTokenRequest;
use App\Services\GitHubConnectionService;
use Illuminate\Http\RedirectResponse;

class GitHubAccountController extends Controller
{
    public function update(UpdateGitHubTokenRequest $request, GitHubConnectionService $github): RedirectResponse
    {
        $token = $request->validated('github_token');

        if (! $token) {
            return back();
        }

        try {
            $profile = $github->connectUserWithToken($request->user(), $token);
        } catch (\Throwable) {
            return back()
                ->withErrors(['github_token' => 'Token GitHub invalide ou expiré.'])
                ->withInput();
        }

        return back()->with('success', 'Compte GitHub connecté ('.$profile['login'].').');
    }

    public function disconnect(GitHubConnectionService $github): RedirectResponse
    {
        $github->disconnectUser(auth()->user());

        return back()->with('success', 'Compte GitHub déconnecté.');
    }
}
