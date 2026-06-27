<?php

namespace App\Http\Controllers\Mcp\OAuth;

use App\Http\Controllers\Controller;
use App\Services\Mcp\McpOAuthService;
use Illuminate\Http\JsonResponse;

class ProtectedResourceController extends Controller
{
    public function __invoke(McpOAuthService $oauth): JsonResponse
    {
        return response()->json($oauth->protectedResourceMetadata());
    }
}
