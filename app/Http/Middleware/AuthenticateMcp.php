<?php

namespace App\Http\Middleware;

use App\Models\McpToken;
use App\Models\User;
use App\Services\Mcp\McpOAuthService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcp
{
    public function __construct(
        private McpOAuthService $oauth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if ($plain === null || $plain === '') {
            return $this->unauthorized($request);
        }

        $user = $this->resolveUserFromStaticToken($plain)
            ?? $this->oauth->findUserByAccessToken($plain);

        if ($user === null) {
            return $this->unauthorized($request);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function resolveUserFromStaticToken(string $plain): ?User
    {
        $mcpToken = McpToken::query()
            ->where('token', hash('sha256', $plain))
            ->with('user')
            ->first();

        if ($mcpToken === null || $mcpToken->user === null) {
            return null;
        }

        $mcpToken->forceFill(['last_used_at' => now()])->save();

        return $mcpToken->user;
    }

    private function unauthorized(Request $request): JsonResponse
    {
        $metadataUrl = $this->oauth->protectedResourceMetadataUrl();

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32001,
                'message' => 'Unauthorized',
            ],
        ], 401, [
            'WWW-Authenticate' => 'Bearer resource_metadata="'.$metadataUrl.'"',
        ]);
    }
}
