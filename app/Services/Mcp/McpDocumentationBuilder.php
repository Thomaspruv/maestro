<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\File;

class McpDocumentationBuilder
{
    public function __construct(
        private readonly McpToolRegistry $registry,
    ) {}

    public function buildMarkdown(): string
    {
        $guide = $this->loadGuide();
        $guide = $this->replacePlaceholders($guide);

        return trim($guide)."\n\n".$this->buildToolsReference();
    }

    private function loadGuide(): string
    {
        $path = base_path('docs/MCP.md');

        if (! File::exists($path)) {
            return '# Documentation MCP indisponible';
        }

        return File::get($path);
    }

    private function replacePlaceholders(string $content): string
    {
        return str_replace(
            ['{{MCP_URL}}', '{{APP_URL}}', '{{APP_NAME}}'],
            [url('/api/mcp'), config('app.url'), config('app.name', 'Maestro')],
            $content,
        );
    }

    private function buildToolsReference(): string
    {
        $lines = [
            '---',
            '',
            '## 13. Référence des tools (générée automatiquement)',
            '',
            'Schémas extraits du serveur MCP à la date de consultation.',
            '',
        ];

        foreach ($this->registry->listTools() as $tool) {
            $lines[] = '### `'.$tool['name'].'`';
            $lines[] = '';
            $lines[] = $tool['description'];
            $lines[] = '';
            $lines[] = '**inputSchema :**';
            $lines[] = '';
            $lines[] = '```json';
            $lines[] = json_encode($tool['inputSchema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $lines[] = '```';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
