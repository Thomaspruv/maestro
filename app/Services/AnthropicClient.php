<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class AnthropicClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly Client $http = new Client(['connect_timeout' => 10]),
    ) {}

    public function validateApiKey(string $key): bool
    {
        try {
            $this->createMessage(
                apiKey: $key,
                model: 'claude-haiku-4-5',
                systemBlocks: [],
                userMessage: 'ping',
                maxTokens: 1,
                timeoutSeconds: 15,
            );

            return true;
        } catch (\Throwable $e) {
            Log::debug('Anthropic API key validation failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $systemBlocks
     * @return array{text: string, usage: array{input_tokens: int, output_tokens: int, cache_read_input_tokens: int}}
     */
    public function createMessage(
        string $apiKey,
        string $model,
        array $systemBlocks,
        string $userMessage,
        int $maxTokens = 4096,
        ?int $timeoutSeconds = null,
    ): array {
        return $this->createConversation(
            $apiKey,
            $model,
            $systemBlocks,
            [['role' => 'user', 'content' => $userMessage]],
            $maxTokens,
            $timeoutSeconds,
        );
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, usage: array{input_tokens: int, output_tokens: int, cache_read_input_tokens: int}}
     */
    public function createConversation(
        string $apiKey,
        string $model,
        array $systemBlocks,
        array $messages,
        int $maxTokens = 4096,
        ?int $timeoutSeconds = null,
    ): array {
        $timeoutSeconds ??= (int) config('maestro.anthropic_timeout', 60);

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];

        if ($systemBlocks !== []) {
            $payload['system'] = $systemBlocks;
        }

        try {
            $response = $this->http->post(self::API_URL, [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => $timeoutSeconds,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Anthropic API request failed: '.$e->getMessage(), 0, $e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return [
            'text' => $text,
            'usage' => [
                'input_tokens' => (int) ($data['usage']['input_tokens'] ?? 0),
                'output_tokens' => (int) ($data['usage']['output_tokens'] ?? 0),
                'cache_read_input_tokens' => (int) ($data['usage']['cache_read_input_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $systemBlocks
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{
     *     text: string,
     *     content: array<int, array<string, mixed>>,
     *     stop_reason: string,
     *     usage: array{input_tokens: int, output_tokens: int, cache_read_input_tokens: int},
     * }
     */
    public function createMessageWithTools(
        string $apiKey,
        string $model,
        array $systemBlocks,
        array $messages,
        array $tools,
        int $maxTokens = 8192,
        ?int $timeoutSeconds = null,
    ): array {
        $timeoutSeconds ??= (int) config('maestro.dev_api_timeout', 120);

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
            'tools' => $tools,
        ];

        if ($systemBlocks !== []) {
            $payload['system'] = $systemBlocks;
        }

        try {
            $response = $this->http->post(self::API_URL, [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                    'anthropic-beta' => 'tools-2024-04-04',
                ],
                'json' => $payload,
                'timeout' => $timeoutSeconds,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Anthropic API request failed: '.$e->getMessage(), 0, $e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return [
            'text' => $text,
            'content' => $data['content'] ?? [],
            'stop_reason' => (string) ($data['stop_reason'] ?? 'end_turn'),
            'usage' => [
                'input_tokens' => (int) ($data['usage']['input_tokens'] ?? 0),
                'output_tokens' => (int) ($data['usage']['output_tokens'] ?? 0),
                'cache_read_input_tokens' => (int) ($data['usage']['cache_read_input_tokens'] ?? 0),
            ],
        ];
    }
}
