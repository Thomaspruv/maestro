<?php

namespace App\Pipeline;

use App\Models\Project;
use App\Services\PipelineRoleCapabilities;

class RunnerFactory
{
    public static function make(string $slug, Project $project): BaseRunner
    {
        $systemPrompt = PipelineRoleCapabilities::resolveSystemPrompt($slug, $project);

        return new GenericApiRunner($systemPrompt);
    }

    public static function makeOrFail(string $slug, Project $project): BaseRunner
    {
        return self::make($slug, $project);
    }
}
