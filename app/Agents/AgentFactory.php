<?php

namespace App\Agents;

use App\Models\Project;
use App\Services\AgentCapabilities;

class AgentFactory
{
    public static function make(string $slug, Project $project): BaseAgent
    {
        $systemPrompt = AgentCapabilities::resolveSystemPrompt($slug, $project);

        return new GenericApiAgent($systemPrompt);
    }

    public static function makeOrFail(string $slug, Project $project): BaseAgent
    {
        return self::make($slug, $project);
    }
}
