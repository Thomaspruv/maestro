<?php

namespace Tests\Unit;

use App\Models\AgentRun;
use App\Models\Task;
use App\Services\DevPromptBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DevPromptBuilderTest extends TestCase
{
    #[Test]
    public function it_keeps_short_agent_outputs_intact(): void
    {
        $run = new AgentRun([
            'input' => ['pm' => 'Specs courtes'],
        ]);
        $run->setRelation('task', new Task(['title' => 'Ma tâche', 'description' => 'Desc']));

        $prompt = app(DevPromptBuilder::class)->build($run);

        $this->assertStringContainsString('### pm', $prompt);
        $this->assertStringContainsString('Specs courtes', $prompt);
    }

    #[Test]
    public function it_condenses_very_long_agent_outputs(): void
    {
        $builder = app(DevPromptBuilder::class);
        $long = str_repeat('A', DevPromptBuilder::MAX_CHARS_PER_AGENT + 500);

        $condensed = $builder->condense($long);

        $this->assertLessThan(strlen($long), strlen($condensed));
        $this->assertStringContainsString('contenu tronqué', $condensed);
    }
}
