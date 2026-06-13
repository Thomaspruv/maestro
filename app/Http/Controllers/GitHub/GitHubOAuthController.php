<?php

namespace App\Http\Controllers\GitHub;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GitHubOAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        session([
            'github_oauth_state' => $state,
            'github_oauth_redirect' => $request->query('redirect', route('projects.create')),
        ]);

        $query = http_build_query([
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => route('github.callback'),
            'scope' => 'repo read:user',
            'state' => $state,
        ]);

        return redirect('https://github.com/login/oauth/authorize?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->query('state');
        $code = $request->query('code');

        if (! $code || ! $state || $state !== session('github_oauth_state')) {
            return redirect()
                ->route('projects.create')
                ->with('error', 'Connexion GitHub échouée (état invalide).');
        }

        session()->forget('github_oauth_state');

        try {
            $client = new Client(['timeout' => 15]);
            $response = $client->post('https://github.com/login/oauth/access_token', [
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
                throw new \RuntimeException('Token GitHub manquant dans la réponse OAuth.');
            }

            session(['github_oauth_token' => $token]);
        } catch (\Throwable $e) {
            Log::warning('GitHub OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('projects.create')
                ->with('error', 'Connexion GitHub échouée.');
        }

        $redirect = session()->pull('github_oauth_redirect', route('projects.create'));

        return redirect($redirect)->with('success', 'Compte GitHub connecté ✓');
    }
}
