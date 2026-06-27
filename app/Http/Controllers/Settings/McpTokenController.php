<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\McpToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class McpTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $plain = Str::random(40);

        McpToken::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'token' => hash('sha256', $plain),
        ]);

        return redirect()
            ->route('settings.mcp')
            ->with('success', 'Token MCP généré. Copiez-le maintenant — il ne sera plus affiché.')
            ->with('mcp_token_plain', $plain);
    }

    public function destroy(Request $request, McpToken $mcpToken): RedirectResponse
    {
        abort_unless($mcpToken->user_id === $request->user()->id, 403);

        $mcpToken->delete();

        return redirect()->route('settings.mcp')->with('success', 'Token MCP révoqué.');
    }
}
