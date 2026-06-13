<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateApiKeyRequest;
use App\Services\AnthropicClient;
use Illuminate\Http\RedirectResponse;

class ApiKeyController extends Controller
{
    public function update(UpdateApiKeyRequest $request, AnthropicClient $anthropic): RedirectResponse
    {
        $apiKey = $request->validated('claude_api_key');

        if (! $anthropic->validateApiKey($apiKey)) {
            return back()->withErrors([
                'claude_api_key' => 'Clé API invalide ou quota dépassé.',
            ]);
        }

        $request->user()->update(['claude_api_key' => $apiKey]);

        return back()->with('success', 'Clé API enregistrée ✓');
    }
}
