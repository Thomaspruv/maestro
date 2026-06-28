<?php

namespace Tests\Unit;

use App\Services\PipelineOutputCondenser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PipelineOutputCondenserTest extends TestCase
{
    #[Test]
    public function it_condenses_unstructured_text_with_head_tail(): void
    {
        $condenser = app(PipelineOutputCondenser::class);
        $long = str_repeat('A', PipelineOutputCondenser::MAX_CHARS_PER_STEP + 500);

        $condensed = $condenser->condense($long);

        $this->assertLessThan(strlen($long), strlen($condensed));
        $this->assertStringContainsString('contenu tronqué', $condensed);
    }

    #[Test]
    public function it_preserves_high_priority_sections_when_condensing(): void
    {
        $condenser = app(PipelineOutputCondenser::class);
        $limit = PipelineOutputCondenser::MAX_CHARS_PER_STEP;

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
        - Risque SQL N+1 sur les pipelineSteps → eager load.
        MD;

        $text = str_repeat($text, 12);

        $this->assertGreaterThan($limit, strlen($text), 'Le texte de test doit dépasser la limite');

        $condensed = $condenser->condense($text);

        $this->assertLessThanOrEqual($limit + 200, strlen($condensed));
        $this->assertStringContainsString("Critères d'acceptation", $condensed);
        $this->assertStringContainsString('Fichiers à modifier', $condensed);
    }

    #[Test]
    public function it_marks_omitted_sections_in_condensed_output(): void
    {
        $condenser = app(PipelineOutputCondenser::class);
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

        if (strlen($text) <= PipelineOutputCondenser::MAX_CHARS_PER_STEP) {
            $this->markTestSkipped('Texte trop court pour tester la condensation.');
        }

        $condensed = $condenser->condense($text);

        $this->assertStringContainsString('Sections omises', $condensed);
    }

    #[Test]
    public function it_returns_empty_string_for_empty_input(): void
    {
        $condenser = app(PipelineOutputCondenser::class);

        $this->assertSame('', $condenser->condense(''));
        $this->assertSame('', $condenser->condense('   '));
    }
}
