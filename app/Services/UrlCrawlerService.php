<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class UrlCrawlerService
{
    public function __construct(
        private readonly Client $http = new Client([
            'timeout' => 15,
            'allow_redirects' => true,
            'headers' => [
                'User-Agent' => 'Maestro-Discovery/1.0',
                'Accept' => 'text/html,application/xhtml+xml,text/plain,*/*',
            ],
        ]),
    ) {}

    public function fetch(string $url, int $maxBytes = 50000, int $timeoutSeconds = 15): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = $this->http->get($url, ['timeout' => $timeoutSeconds]);

            if ($response->getStatusCode() !== 200) {
                Log::debug('URL crawl non-200', ['url' => $url, 'status' => $response->getStatusCode()]);

                return null;
            }

            $body = (string) $response->getBody();

            if (strlen($body) > $maxBytes) {
                $body = substr($body, 0, $maxBytes).'…';
            }

            return $body !== '' ? $body : null;
        } catch (GuzzleException $e) {
            Log::debug('URL crawl failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    public function extractUrls(string $text): array
    {
        preg_match_all('#https?://[^\s<>"\'\)\]]+#i', $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
