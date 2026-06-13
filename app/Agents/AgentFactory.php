<?php

namespace App\Agents;

use App\Enums\AgentType;
use App\Models\Project;
use Database\Seeders\AgentPromptSeeder;
use InvalidArgumentException;

class AgentFactory
{
    public static function make(AgentType|string $type, Project $project): BaseAgent
    {
        $agentType = $type instanceof AgentType ? $type : AgentType::from($type);
        $typeValue = $agentType->value;

        $customAgent = $project->agents()->where('agent_type', $agentType)->first();
        $systemPrompt = $customAgent?->system_prompt ?? self::defaultPrompt($agentType);

        return match ($agentType) {
            AgentType::Pm => new PmAgent($systemPrompt),
            AgentType::Ux => new UxAgent($systemPrompt),
            AgentType::TechLead => new TechLeadAgent($systemPrompt),
            AgentType::Security => new SecurityAgent($systemPrompt),
            AgentType::Dev => new DevAgent($systemPrompt),
            AgentType::Qa => new QaAgent($systemPrompt),
            AgentType::PrExpert => new PrExpertAgent($systemPrompt),
            AgentType::Doc => new DocAgent($systemPrompt),
        };
    }

    public static function defaultPrompt(AgentType $type): string
    {
        return AgentPromptSeeder::for($type);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function makeOrFail(AgentType|string $type, Project $project): BaseAgent
    {
        try {
            return self::make($type, $project);
        } catch (\ValueError $e) {
            throw new InvalidArgumentException("Agent inconnu : {$type}", 0, $e);
        }
    }
}
