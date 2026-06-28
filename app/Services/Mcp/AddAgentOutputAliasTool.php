<?php

namespace App\Services\Mcp;

use App\Models\User;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\Tools\RecordStepOutputTool;

class AddAgentOutputAliasTool implements McpTool
{
    public function __construct(
        private readonly RecordStepOutputTool $delegate,
    ) {}

    public function name(): string
    {
        return 'add_agent_output';
    }

    public function description(): string
    {
        return $this->delegate->description().' (alias legacy — préférez record_step_output).';
    }

    public function inputSchema(): array
    {
        $schema = $this->delegate->inputSchema();
        $schema['properties']['agent_type'] = ['type' => 'string', 'description' => 'Alias legacy de role'];
        $schema['required'] = ['task_id', 'output', 'model'];

        return $schema;
    }

    public function execute(array $arguments, User $user): array
    {
        if (! isset($arguments['role']) && isset($arguments['agent_type'])) {
            $arguments['role'] = $arguments['agent_type'];
        }

        return $this->delegate->execute($arguments, $user);
    }
}
