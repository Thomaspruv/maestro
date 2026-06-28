<?php

namespace Database\Seeders;

use App\Enums\PipelineStepStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskMode;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\PipelineStep;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\CostEstimatorService;
use App\Services\ProjectRoleSyncService;
use Illuminate\Database\Seeder;

class LocalFakeProjectSeeder extends Seeder
{
    public const PROJECT_UUID = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

    public const USER_EMAIL = 'thomas@mail.com';

    public function run(): void
    {
        if (! app()->environment('local') && ! app()->runningUnitTests()) {
            $this->command?->warn('LocalFakeProjectSeeder : réservé à l\'environnement local.');

            return;
        }

        $user = User::query()->where('email', self::USER_EMAIL)->first();

        if ($user === null) {
            $this->command?->error('Utilisateur '.self::USER_EMAIL.' introuvable. Connectez-vous une fois ou lancez maestro:restore-thomas.');

            return;
        }

        PipelineRoleSeeder::seedForUser($user);

        $project = Project::query()->updateOrCreate(
            ['uuid' => self::PROJECT_UUID],
            [
                'user_id' => $user->id,
                'name' => 'Sandbox Local',
                'description' => 'Projet fake pour tester le Kanban, Hermes et le MCP en local.',
                'github_repo' => 'acme/sandbox-app',
                'github_branch' => 'main',
                'context' => [
                    'stack' => ['Laravel 13', 'Livewire 3', 'Tailwind CSS', 'PostgreSQL', 'Redis'],
                    'conventions' => [
                        'TALL Stack obligatoire',
                        'Services pour la logique métier',
                        'UI en français',
                    ],
                    'modules' => ['Auth', 'Dashboard', 'Notifications'],
                    'design_system' => [
                        'Thème' => 'dark Maestro',
                        'Composants' => 'x-maestro.*',
                    ],
                    'constraints' => [
                        'Ne jamais vider la base dev',
                        'Tests sur SQLite in-memory uniquement',
                    ],
                ],
                'pipeline_config' => DefaultPipelineSeeder::pipelines(),
                'gate_config' => DefaultPipelineSeeder::gateConfig(),
                'default_modes' => DefaultPipelineSeeder::defaultModes(),
                'model_config' => config('maestro.default_models'),
                'status' => ProjectStatus::Active,
            ],
        );

        if ($project->roles()->count() === 0) {
            $modelConfig = app(ProjectRoleSyncService::class)->copyUserRolesToProject($user, $project);
            $project->update(['model_config' => $modelConfig]);
        }

        $estimator = app(CostEstimatorService::class);

        $tasks = [
            [
                'uuid' => 'b1111111-1111-4111-8111-111111111111',
                'title' => 'Backlog — Ajouter export CSV des tâches',
                'description' => 'Exporter les tâches du projet en CSV depuis le Kanban.',
                'module' => 'Tasks',
                'type' => TaskType::Feature,
                'priority' => TaskPriority::Medium,
                'status' => TaskStatus::Backlog,
                'mode' => TaskMode::Manual,
                'sort_order' => 1,
            ],
            [
                'uuid' => 'b2222222-2222-4222-8222-222222222222',
                'title' => 'En cours — Refactor PipelineHealthService',
                'description' => 'Simplifier les états affichés sur les cartes Kanban.',
                'module' => 'Agents',
                'type' => TaskType::Improvement,
                'priority' => TaskPriority::High,
                'status' => TaskStatus::InProgress,
                'mode' => TaskMode::Manual,
                'current_role' => 'pm',
                'sort_order' => 2,
            ],
            [
                'uuid' => 'b3333333-3333-4333-8333-333333333333',
                'title' => 'Hermes — Implémenter notifications email',
                'description' => 'Après validation Tech Lead : envoyer un email quand une gate est en attente.',
                'module' => 'Notifications',
                'type' => TaskType::Feature,
                'priority' => TaskPriority::Critical,
                'status' => TaskStatus::WaitingHermes,
                'mode' => TaskMode::Manual,
                'current_role' => 'hermes',
                'sort_order' => 3,
                'with_planning_runs' => true,
            ],
            [
                'uuid' => 'b4444444-4444-4444-8444-444444444444',
                'title' => 'En revue — PR dashboard coûts',
                'description' => 'Graphique des coûts par agent sur 30 jours.',
                'module' => 'Costs',
                'type' => TaskType::Feature,
                'priority' => TaskPriority::Low,
                'status' => TaskStatus::InReview,
                'mode' => TaskMode::SemiAuto,
                'sort_order' => 4,
            ],
            [
                'uuid' => 'b5555555-5555-4555-8555-555555555555',
                'title' => 'Terminé — Fix tri Kanban',
                'description' => 'Le drag & drop persistait mal entre colonnes.',
                'module' => 'Tasks',
                'type' => TaskType::Bug,
                'priority' => TaskPriority::High,
                'status' => TaskStatus::Done,
                'mode' => TaskMode::Manual,
                'sort_order' => 5,
            ],
        ];

        foreach ($tasks as $taskData) {
            $withPlanning = (bool) ($taskData['with_planning_runs'] ?? false);
            unset($taskData['with_planning_runs']);

            $task = Task::query()->updateOrCreate(
                ['uuid' => $taskData['uuid']],
                array_merge($taskData, ['project_id' => $project->id]),
            );

            if ($task->estimated_cost === null) {
                $estimate = $estimator->estimate($task);
                $task->update(['estimated_cost' => $estimate['total_mid']]);
            }

            if ($withPlanning) {
                $this->seedPlanningRuns($task);
            }
        }

        $this->command?->info('Projet fake créé : '.route('projects.show', $project));
        $this->command?->info('Compte : '.self::USER_EMAIL);
        $this->command?->line('  → 5 tâches (backlog, en cours, Hermes, revue, terminé)');
    }

    private function seedPlanningRuns(Task $task): void
    {
        $task->pipelineSteps()->delete();

        foreach (['pm', 'ux', 'tech_lead', 'security'] as $agentType) {
            PipelineStep::create([
                'task_id' => $task->id,
                'role' => $agentType,
                'status' => PipelineStepStatus::Completed,
                'input' => [],
                'output' => "Output fake {$agentType} — specs prêtes pour Hermes.",
                'model' => config("maestro.default_models.{$agentType}", 'claude-sonnet-4-6'),
                'attempt' => 1,
                'started_at' => now()->subHour(),
                'completed_at' => now()->subMinutes(55),
            ]);
        }
    }
}
