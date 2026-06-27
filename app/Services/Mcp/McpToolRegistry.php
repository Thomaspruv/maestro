<?php

namespace App\Services\Mcp;

use App\Models\User;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\Tools\AddAgentOutputTool;
use App\Services\Mcp\Tools\ClaimHermesTaskTool;
use App\Services\Mcp\Tools\CreateTaskTool;
use App\Services\Mcp\Tools\GetTaskTool;
use App\Services\Mcp\Tools\ListHermesTasksTool;
use App\Services\Mcp\Tools\ListProjectsTool;
use App\Services\Mcp\Tools\ListTasksTool;
use App\Services\Mcp\Tools\LogCostTool;
use App\Services\Mcp\Tools\RequestGateTool;
use App\Services\Mcp\Tools\UpdateTaskStatusTool;

class McpToolRegistry
{
    /**
     * @var array<string, McpTool>
     */
    private array $tools;

    public function __construct(
        ListProjectsTool $listProjects,
        ListTasksTool $listTasks,
        ListHermesTasksTool $listHermesTasks,
        GetTaskTool $getTask,
        CreateTaskTool $createTask,
        UpdateTaskStatusTool $updateTaskStatus,
        AddAgentOutputTool $addAgentOutput,
        ClaimHermesTaskTool $claimHermesTask,
        RequestGateTool $requestGate,
        LogCostTool $logCost,
    ) {
        $this->tools = collect([
            $listProjects,
            $listTasks,
            $listHermesTasks,
            $getTask,
            $createTask,
            $updateTaskStatus,
            $addAgentOutput,
            $claimHermesTask,
            $requestGate,
            $logCost,
        ])->keyBy(fn (McpTool $tool) => $tool->name())->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTools(): array
    {
        return collect($this->tools)
            ->map(fn (McpTool $tool) => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'inputSchema' => $tool->inputSchema(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments, User $user): array
    {
        $tool = $this->tools[$name] ?? null;

        if ($tool === null) {
            throw McpToolException::notFound("tool:{$name}");
        }

        return $tool->execute($arguments, $user);
    }
}
