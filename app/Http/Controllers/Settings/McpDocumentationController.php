<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Mcp\McpDocumentationBuilder;
use Illuminate\Support\Str;
use Illuminate\View\View;

class McpDocumentationController extends Controller
{
    public function show(McpDocumentationBuilder $builder): View
    {
        return view('settings.mcp-docs', [
            'mcpUrl' => url('/api/mcp'),
            'docsUrl' => route('settings.mcp.docs'),
            'html' => Str::markdown($builder->buildMarkdown()),
        ]);
    }
}
