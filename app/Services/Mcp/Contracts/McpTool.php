<?php

namespace App\Services\Mcp\Contracts;

use App\Models\User;

interface McpTool
{
    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, User $user): array;
}
