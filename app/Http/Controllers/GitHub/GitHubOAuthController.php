<?php

namespace App\Http\Controllers\GitHub;

use App\Http\Controllers\Controller;
use App\Services\GitHubConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GitHubOAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! app(GitHubConnectionService::class)->isOAuthConfigured()) {
            return redirect()
                ->back()
                ->with('error', 'OAuth GitHub non configuré. Utilisez un token personnel dans Paramètres.');
        }

        $state = Str::random(40);
        session([
            'github_oauth_state' => $state,
            'github_oauth_redirect' => $request->query('redirect', route('settings.edit')),
        ]);

        $query = http_build_query([
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => route('github.callback'),
            'scope' => 'repo read:user',
            'state' => $state,
        ]);

        return redirect('https://github.com/login/oauth/authorize?'.$query);
    }

    public function callback(Request $request, GitHubConnectionService $github): RedirectResponse
    {
        $state = $request->query('state');
        $code = $request->query('code');
        $fallback = route('settings.edit');

        if (! $code || ! $state || $state !== session('github_oauth_state')) {
            return redirect($fallback)
                ->with('error', 'Connexion GitHub échouée (état invalide).');
        }

        session()->forget('github_oauth_state');

        try {
            $connection = $github->connectWithOAuthCode($code);

            $request->user()->update([
                'github_token' => $connection['token'],
                'github_username' => $connection['username'],
                'github_connected_at' => now(),
            ]);

            session(['github_oauth_token' => $connection['token']]);
        } catch (\Throwable $e) {
            Log::warning('GitHub OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect($fallback)
                ->with('error', 'Connexion GitHub échouée : '.$e->getMessage());
        }

        $redirect = session()->pull('github_oauth_redirect', $fallback);

        return redirect($redirect)->with('success', 'Compte GitHub connecté (@'.$connection['username'].').');
    }
}
