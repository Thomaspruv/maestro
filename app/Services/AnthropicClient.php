<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class AnthropicClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly Client $http = new Client(['timeout' => 30]),
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
    ): array {
        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
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
}
