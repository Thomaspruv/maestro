<?php

namespace App\Http\Controllers\Mcp\OAuth;

use App\Http\Controllers\Controller;
use App\Services\Mcp\McpOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthorizeController extends Controller
{
    public function show(Request $request, McpOAuthService $oauth): View|RedirectResponse
    {
        $params = $this->validatedAuthorizeParams($request);

        if ($params instanceof RedirectResponse) {
            return $params;
        }

        if (! $request->user()) {
            return redirect()->guest(route('oauth.mcp.authorize', $request->query()));
        }

        return view('oauth.mcp-consent', [
            'clientName' => $params['client']->client_name ?? 'Application MCP',
            'scopes' => config('maestro.mcp.oauth.scopes'),
            'authorize' => $params,
        ]);
    }

    public function approve(Request $request, McpOAuthService $oauth): RedirectResponse
    {
        $params = $this->validatedAuthorizeParams($request);

        if ($params instanceof RedirectResponse) {
            return $params;
        }

        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $issued = $oauth->issueAuthorizationCode(
            $params['client'],
            $user,
            $params['redirect_uri'],
            $params['code_challenge'],
            $params['code_challenge_method'],
            $params['scope'],
        );

        return redirect()->away($this->buildRedirect(
            $params['redirect_uri'],
            $issued['plain'],
            $params['state'],
        ));
    }

    public function deny(Request $request): RedirectResponse
    {
        $redirectUri = $request->string('redirect_uri')->toString();
        $state = $request->string('state')->toString();

        if ($redirectUri === '') {
            abort(400, 'redirect_uri required');
        }

        $query = http_build_query([
            'error' => 'access_denied',
            'error_description' => 'The user denied the request',
            'state' => $state !== '' ? $state : null,
        ]);

        return redirect()->away($redirectUri.'?'.$query);
    }

    /**
     * @return array<string, mixed>|RedirectResponse
     */
    private function validatedAuthorizeParams(Request $request): array|RedirectResponse
    {
        $clientId = $request->string('client_id')->toString();
        $redirectUri = $request->string('redirect_uri')->toString();
        $responseType = $request->string('response_type')->toString();
        $codeChallenge = $request->string('code_challenge')->toString();
        $codeChallengeMethod = $request->string('code_challenge_method', 'S256')->toString();
        $state = $request->string('state')->toString();
        $scope = $request->string('scope')->toString() ?: null;

        if ($clientId === '' || $redirectUri === '' || $responseType !== 'code') {
            return $this->errorRedirect($redirectUri, 'invalid_request', $state);
        }

        if ($codeChallenge === '' || $codeChallengeMethod !== 'S256') {
            return $this->errorRedirect($redirectUri, 'invalid_request', $state);
        }

        $oauth = app(McpOAuthService::class);
        $client = $oauth->findClient($clientId);

        if ($client === null || ! $client->allowsRedirectUri($redirectUri)) {
            return $this->errorRedirect($redirectUri, 'invalid_client', $state);
        }

        return [
            'client' => $client,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => $responseType,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'state' => $state,
            'scope' => $scope,
        ];
    }

    private function buildRedirect(string $redirectUri, string $code, string $state): string
    {
        $query = http_build_query(array_filter([
            'code' => $code,
            'state' => $state !== '' ? $state : null,
        ]));

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri.$separator.$query;
    }

    private function errorRedirect(string $redirectUri, string $error, string $state): RedirectResponse
    {
        if ($redirectUri === '') {
            abort(400, $error);
        }

        $query = http_build_query(array_filter([
            'error' => $error,
            'state' => $state !== '' ? $state : null,
        ]));

        return redirect()->away($redirectUri.'?'.$query);
    }
}
