<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\McpToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpController extends Controller
{
    private const PROTOCOL_VERSION = '2024-11-05';

    public function __invoke(Request $request, McpToolRegistry $registry): JsonResponse
    {
        $payload = $request->json()->all();

        if (! is_array($payload) || ($payload['jsonrpc'] ?? null) !== '2.0') {
            return $this->errorResponse(null, -32600, 'Invalid Request');
        }

        $id = $payload['id'] ?? null;

        if ($id === null) {
            return response()->json(null, 204);
        }

        $method = $payload['method'] ?? '';
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'tools/list' => ['tools' => $registry->listTools()],
                'tools/call' => $this->callTool($registry, $request, $params),
                default => null,
            };

            if ($result === null) {
                return $this->errorResponse($id, -32601, "Method not found: {$method}");
            }

            return $this->successResponse($id, $result);
        } catch (McpToolException $exception) {
            return $this->errorResponse($id, -32602, $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => (object) [],
            ],
            'serverInfo' => [
                'name' => 'maestro',
                'version' => '2.0.0',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function callTool(McpToolRegistry $registry, Request $request, array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if (! is_string($name) || $name === '') {
            throw McpToolException::missing('name');
        }

        $user = $request->user();
        $data = $registry->call($name, $arguments, $user);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ],
            ],
            'isError' => false,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function successResponse(mixed $id, ?array $result): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    private function errorResponse(mixed $id, int $code, string $message): JsonResponse
    {
        $status = $code === -32601 ? 404 : ($code === -32600 ? 400 : 422);

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
