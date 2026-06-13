<?php

namespace Tests\Feature;

use App\Livewire\ProjectWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_step1_shows_validation_errors_when_only_name_is_filled(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProjectWizard::class)
            ->set('name', 'Mon projet')
            ->call('saveStep1')
            ->assertHasErrors(['github_repo'])
            ->assertSee('Impossible de continuer')
            ->assertSee('Le dépôt GitHub est obligatoire');
    }

    public function test_step1_advances_with_required_fields(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProjectWizard::class)
            ->set('name', 'Mon projet')
            ->set('github_repo', 'owner/repo')
            ->set('github_branch', 'main')
            ->call('saveStep1')
            ->assertHasNoErrors()
            ->assertSet('step', 2);
    }
}
