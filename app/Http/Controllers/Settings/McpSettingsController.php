<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class McpSettingsController extends Controller
{
    public function edit(): View
    {
        $user = auth()->user();

        return view('settings.mcp', [
            'mcpTokens' => $user->mcpTokens()->latest()->get(),
            'mcpUrl' => url('/api/mcp'),
        ]);
    }
}
