<?php

namespace App\DTOs;

readonly class PipelineStepResult
{
    public function __construct(
        public string $output,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cachedTokens = 0,
        public float $cost = 0.0,
        public ?string $prUrl = null,
        public ?string $prBranch = null,
    ) {}
}
