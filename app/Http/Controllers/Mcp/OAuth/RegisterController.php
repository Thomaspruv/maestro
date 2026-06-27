<?php

namespace App\Http\Controllers\Mcp\OAuth;

use App\Http\Controllers\Controller;
use App\Services\Mcp\McpOAuthException;
use App\Services\Mcp\McpOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function __invoke(Request $request, McpOAuthService $oauth): JsonResponse
    {
        $payload = $request->json()->all();

        if (! is_array($payload)) {
            return $this->error('invalid_client_metadata', 'Invalid JSON body', 400);
        }

        try {
            return response()->json($oauth->registerClient($payload), 201);
        } catch (McpOAuthException $exception) {
            return $this->error($exception->error, $exception->getMessage(), 400);
        }
    }

    private function error(string $error, string $description, int $status): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'error_description' => $description,
        ], $status);
    }
}
