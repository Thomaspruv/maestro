<?php

namespace App\Http\Controllers\Mcp\OAuth;

use App\Http\Controllers\Controller;
use App\Services\Mcp\McpOAuthException;
use App\Services\Mcp\McpOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function __invoke(Request $request, McpOAuthService $oauth): JsonResponse
    {
        $grantType = $request->input('grant_type');

        try {
            $result = match ($grantType) {
                'authorization_code' => $oauth->exchangeAuthorizationCode(
                    (string) $request->input('code', ''),
                    (string) $request->input('client_id', ''),
                    (string) $request->input('redirect_uri', ''),
                    (string) $request->input('code_verifier', ''),
                ),
                'refresh_token' => $oauth->refreshAccessToken(
                    (string) $request->input('refresh_token', ''),
                    (string) $request->input('client_id', ''),
                ),
                default => throw new McpOAuthException('unsupported_grant_type', 'Unsupported grant_type'),
            };

            return response()->json($result);
        } catch (McpOAuthException $exception) {
            $status = in_array($exception->error, ['invalid_client'], true) ? 401 : 400;

            return response()->json([
                'error' => $exception->error,
                'error_description' => $exception->getMessage(),
            ], $status);
        }
    }
}
