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
    public function it_condenses_unstructured_text_with_head_tail(): void
    {
        $builder = app(DevPromptBuilder::class);
        $long = str_repeat('A', DevPromptBuilder::MAX_CHARS_PER_AGENT + 500);

        $condensed = $builder->condense($long);

        $this->assertLessThan(strlen($long), strlen($condensed));
        $this->assertStringContainsString('contenu tronqué', $condensed);
    }

    #[Test]
    public function it_preserves_high_priority_sections_when_condensing(): void
    {
        $builder = app(DevPromptBuilder::class);
        $limit = DevPromptBuilder::MAX_CHARS_PER_AGENT;

        $filler = str_repeat('x', 300);
        $text = <<<MD
        ## 1. Wireframes
        {$filler}

        ## 2. Exemples de maquettes
        {$filler}

        ## 3. Critères d'acceptation
        AC-01 : L'utilisateur peut filtrer par période.
        AC-02 : Le tableau se rafraîchit sans rechargement.

        ## 4. Fichiers à modifier
        - app/Livewire/VelocityDashboard.php
        - resources/views/livewire/velocity-dashboard.blade.php

        ## 5. Risques et mitigations
        - Risque SQL N+1 sur les agentRuns → eager load.
        MD;

        $text = str_repeat($text, 12);

        $this->assertGreaterThan($limit, strlen($text), 'Le texte de test doit dépasser la limite');

        $condensed = $builder->condense($text);

        $this->assertLessThanOrEqual($limit + 200, strlen($condensed));
        $this->assertStringContainsString("Critères d'acceptation", $condensed);
        $this->assertStringContainsString('Fichiers à modifier', $condensed);
    }

    #[Test]
    public function it_marks_omitted_sections_in_condensed_output(): void
    {
        $builder = app(DevPromptBuilder::class);
        $filler = str_repeat('Contenu générique. ', 200);

        $text = <<<MD
        ## 1. Résumé
        {$filler}

        ## 2. Parcours utilisateur
        {$filler}

        ## 3. Wireframes détaillés
        {$filler}

        ## 4. Critères d'acceptation
        AC-01 : Critère testable.

        ## 5. Risques
        Risque identifié.
        MD;

        if (strlen($text) <= DevPromptBuilder::MAX_CHARS_PER_AGENT) {
            $this->markTestSkipped('Texte trop court pour tester la condensation.');
        }

        $condensed = $builder->condense($text);

        $this->assertStringContainsString('Sections omises', $condensed);
    }

    #[Test]
    public function it_returns_empty_string_for_empty_input(): void
    {
        $builder = app(DevPromptBuilder::class);

        $this->assertSame('', $builder->condense(''));
        $this->assertSame('', $builder->condense('   '));
    }
}
