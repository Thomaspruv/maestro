<?php

namespace App\Enums;

enum PipelineRoleSlug: string
{
    case Pm = 'pm';
    case Ux = 'ux';
    case TechLead = 'tech_lead';
    case Security = 'security';
    case Dev = 'dev';
    case Qa = 'qa';
    case PrExpert = 'pr_expert';
    case Doc = 'doc';

    public function label(): string
    {
        $config = config('maestro.role_labels.'.$this->value);

        if (! is_array($config)) {
            return $this->value;
        }

        $emoji = $config['emoji'] ?? '';
        $name = $config['name'] ?? $this->value;

        return trim("{$emoji} {$name}");
    }
}
