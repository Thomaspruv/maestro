<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGitHubWebhook
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Hub-Signature-256');
        $payload = $request->getContent();
        $secret = config('services.github.webhook_secret');
        $expected = 'sha256='.hash_hmac('sha256', $payload, (string) $secret);

        abort_unless(hash_equals($expected, $signature ?? ''), 401);

        return $next($request);
    }
}
