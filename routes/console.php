<?php

use App\Jobs\CheckBudgetAlertsJob;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\UserAgent;
use Database\Seeders\ThomasRestoreSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('maestro:restore-thomas', function () {
    $this->call(ThomasRestoreSeeder::class);
})->purpose('Restaure le compte thomas@mail.com, le projet Maestro et la tâche pipeline UI');

Artisan::command('maestro:sync-dev-model', function () {
    $model = config('maestro.default_models.dev', 'claude-haiku-4-5');

    $userAgents = UserAgent::query()->where('slug', 'dev')->update(['model' => $model]);
    $projectAgents = ProjectAgent::query()->where('agent_type', 'dev')->update(['model' => $model]);

    $projectsUpdated = 0;

    Project::query()->each(function (Project $project) use ($model, &$projectsUpdated): void {
        $config = $project->model_config ?? [];

        if (($config['dev'] ?? null) === $model) {
            return;
        }

        $config['dev'] = $model;
        $project->update(['model_config' => $config]);
        $projectsUpdated++;
    });

    $this->info("Modèle Dev synchronisé sur {$model}.");
    $this->line("  UserAgents : {$userAgents}");
    $this->line("  ProjectAgents : {$projectAgents}");
    $this->line("  Projects (model_config) : {$projectsUpdated}");
})->purpose('Passe l\'agent Dev sur le modèle par défaut (Haiku) pour tous les comptes et projets');

Schedule::job(new CheckBudgetAlertsJob)->daily();
