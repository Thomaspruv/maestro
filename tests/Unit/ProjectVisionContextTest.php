<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Services\PipelineStepRunnerService;
use App\Services\DiscoveryChatService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectVisionContextTest extends TestCase
{
    #[Test]
    public function vision_is_included_in_pipeline_agent_context(): void
    {
        $project = new Project([
            'name' => 'Maestro',
            'context' => [
                'vision' => 'Orchestrer des agents IA pour les product owners.',
                'stack' => 'Laravel',
                'conventions' => 'PSR-12',
                'modules' => 'Tasks',
                'design_system' => 'TALL',
                'constraints' => 'Aucune dépendance payante',
            ],
        ]);

        $context = app(PipelineStepRunnerService::class)->buildProjectContext($project);

        $this->assertStringContainsString('### Vision produit', $context);
        $this->assertStringContainsString('Orchestrer des agents IA', $context);
    }

    #[Test]
    public function vision_is_prioritized_in_discovery_product_context(): void
    {
        $service = new \ReflectionClass(DiscoveryChatService::class);
        $method = $service->getMethod('buildProductContext');
        $method->setAccessible(true);

        $project = new Project([
            'name' => 'Maestro',
            'description' => 'Description courte',
            'context' => [
                'vision' => 'Vision stratégique longue.',
                'modules' => 'Kanban, Agents',
                'design_system' => 'Dark UI',
            ],
        ]);

        $context = $method->invoke(app(DiscoveryChatService::class), $project);

        $this->assertStringContainsString('### Vision', $context);
        $this->assertStringContainsString('Vision stratégique longue.', $context);
        $this->assertStringContainsString('alignées sur cette vision', $context);
        $this->assertStringNotContainsString('Description courte', $context);
    }
}
