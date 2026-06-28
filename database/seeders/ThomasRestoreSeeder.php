<?php

namespace Database\Seeders;

use App\Enums\PipelineStepStatus;
use App\Enums\GateStatus;
use App\Enums\GateType;
use App\Enums\ProjectStatus;
use App\Enums\TaskMode;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\PipelineStep;
use App\Models\Gate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectRoleSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ThomasRestoreSeeder extends Seeder
{
    public const USER_EMAIL = 'thomas@mail.com';

    public const USER_PASSWORD = 'password';

    public const PROJECT_UUID = '4c14ec65-61d4-4af6-a0d4-c97c8bbea1c3';

    public const TASK_UUID = '80b34b8b-d574-4f65-896b-75197bf55975';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('ThomasRestoreSeeder : réservé à l\'environnement local.');

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => self::USER_EMAIL],
            [
                'name' => 'Thomas',
                'password' => Hash::make(self::USER_PASSWORD),
                'email_verified_at' => now(),
                'github_username' => 'Thomaspruv',
                'github_connected_at' => now(),
                'claude_api_key' => filled(env('ANTHROPIC_API_KEY')) ? env('ANTHROPIC_API_KEY') : null,
                'github_token' => filled(env('GITHUB_TOKEN')) ? env('GITHUB_TOKEN') : null,
                'monthly_budget' => null,
                'notification_preferences' => [
                    'gate_pending' => true,
                    'pipeline_failed' => true,
                    'budget_alert' => true,
                ],
            ],
        );

        PipelineRoleSeeder::seedForUser($user);
        PipelineRoleSeeder::refreshBuiltinModels($user);

        $modelConfig = config('maestro.default_models', []);
        $modelConfig['dev'] = 'claude-haiku-4-5';

        $project = Project::query()->updateOrCreate(
            ['uuid' => self::PROJECT_UUID],
            [
                'user_id' => $user->id,
                'name' => 'Maestro',
                'description' => 'Orchestrateur d\'agents IA — projet dogfooding sur Thomaspruv/maestro.',
                'github_repo' => 'Thomaspruv/maestro',
                'github_branch' => 'main',
                'context' => $this->projectContext(),
                'pipeline_config' => DefaultPipelineSeeder::pipelines(),
                'gate_config' => DefaultPipelineSeeder::gateConfig(),
                'default_modes' => DefaultPipelineSeeder::defaultModes(),
                'model_config' => $modelConfig,
                'status' => ProjectStatus::Active,
            ],
        );

        if ($project->roles()->count() === 0) {
            $synced = app(ProjectRoleSyncService::class)->copyUserRolesToProject($user, $project);
            $project->update(['model_config' => array_merge($modelConfig, $synced)]);
        } else {
            $project->update(['model_config' => $modelConfig]);
            foreach ($project->roles as $agent) {
                if ($agent->role === 'dev') {
                    $agent->update(['model' => 'claude-haiku-4-5']);
                }
            }
        }

        $task = Task::query()->updateOrCreate(
            ['uuid' => self::TASK_UUID],
            [
                'project_id' => $project->id,
                'title' => 'Améliorer le feedback visuel pendant l\'exécution d\'un agent',
                'description' => 'Afficher un indicateur de progression clair (PipelineHealthService, timeline, drawer Kanban) pendant l\'exécution des agents. Messages explicites si worker inactif, gate en attente, ou run zombie.',
                'module' => 'Agents',
                'type' => TaskType::Improvement,
                'priority' => TaskPriority::High,
                'status' => TaskStatus::InProgress,
                'mode' => TaskMode::Manual,
                'current_role' => 'dev',
                'sort_order' => 1,
            ],
        );

        $this->restorePipelineProgress($task);

        $this->command?->info('Compte restauré : '.self::USER_EMAIL.' / '.self::USER_PASSWORD);
        $this->command?->info('Projet : '.route('projects.show', $project));
        $this->command?->info('Tâche : '.route('projects.tasks.show', [$project, $task]));

        if (! $user->hasGithubConnection()) {
            $this->command?->warn('Token GitHub absent — reconnectez GitHub dans Paramètres.');
        }

        if (! filled($user->claude_api_key)) {
            $this->command?->warn('Clé API Claude absente — renseignez-la dans Paramètres.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function projectContext(): array
    {
        return [
            'vision' => 'Maestro orchestre une pipeline d\'agents IA (PM → UX → Tech Lead → Security → Dev → QA → PR → Doc) pour développeurs solo. Dogfooding sur ce dépôt.',
            'stack' => <<<'TEXT'
Backend : Laravel 13, PHP 8.3
Frontend : TALL Stack — Livewire 3, Volt (auth Breeze), Alpine.js, Tailwind CSS 3, Vite 8
Base de données : MySQL en dev local, PostgreSQL 15 en prod
Queues : Laravel Horizon + Redis (queues agents, REMOVED_DEV_AGENT) — dev local souvent QUEUE_CONNECTION=database
Temps réel : Laravel Echo + Pusher/Soketi (BROADCAST_CONNECTION=log en local)
IA : Claude API (Anthropic) via AnthropicClient — agents texte
IA code : Claude Code CLI — Dev Agent uniquement
GitHub : knplabs/github-api — OAuth/PAT, branches, PRs, webhooks
Auth : Laravel Breeze Livewire
Tests : PHPUnit 12
Repo : https://github.com/Thomaspruv/maestro
TEXT,
            'conventions' => <<<'TEXT'
Architecture : Controllers fins, Services métier (OrchestratorService, PipelineStepRunnerService, DevPipelineStepner), Jobs queue
Validation : Form Requests
Nommage : anglais pour le code, français pour l'UI utilisateur
Agents : slugs string (pm, ux, tech_lead, security, dev, qa, pr_expert, doc, discovery)
Composants UI : x-maestro.* Blade components, thème dark (bg-base, text-primary, etc.)
Livewire : composants KanbanBoard, TaskPipeline, StepOutputViewer, ProjectSettings
TEXT,
            'modules' => <<<'TEXT'
Projects & wizard (contexte, pipeline, modèles)
Tasks & Kanban board
Pipeline agents + gates (specs, tech, merge)
Agent library (PipelineRole / ProjectRole)
Discovery chat
Coûts & budget
GitHub (connexion compte, dépôt projet, PR)
Paramètres utilisateur (clé Claude, GitHub)
TEXT,
            'design_system' => <<<'TEXT'
Thème dark Maestro : primary violet (#7c3aed), surfaces bg-base/surface/elevated
Typo : text-xs à text-sm, labels text-text-muted
Composants : maestro-card, maestro-button, badges statut (success/warning/danger)
Sidebar 220px, drawer tâche 3 colonnes (timeline | output | actions)
Emojis agents via config maestro.role_labels
TEXT,
            'constraints' => <<<'TEXT'
Desktop-first (message si viewport < 1200px)
Secrets chiffrés (claude_api_key, github_token)
Dev Agent : clone unique par projet dans storage/repos, branche feature/{task-uuid}-{slug}
Mode manuel par défaut pour les features (gates specs + tech + merge)
Pas de force-push ; PR ouverte après PR Expert
TEXT,
        ];
    }

    private function restorePipelineProgress(Task $task): void
    {
        $task->pipelineSteps()->delete();
        $task->gates()->delete();

        $models = config('maestro.default_models', []);
        $models['dev'] = 'claude-haiku-4-5';

        $completedAgents = [
            'pm' => "Spec produit — feedback visuel pipeline\n\n## Résumé\nAméliorer la visibilité de l'état pipeline pendant l'exécution des agents.\n\n## User stories\n- En tant qu'utilisateur, je vois quel agent tourne et si le worker est actif\n- En tant qu'utilisateur, je comprends quand une gate bloque la pipeline\n\n## Critères d'acceptation\n- Bandeau PipelineHealthService avec états explicites\n- Timeline Kanban synchronisée avec PipelineStepUpdated\n- Message si queue database sans worker",
            'ux' => "UX — wireframes textuels\n\n1. Bandeau santé pipeline en haut du drawer (couleur selon état)\n2. Timeline gauche : étape courante pulsante, gates en jaune\n3. Footer gate sticky avec Approuver/Rejeter toujours visible\n4. État « worker bloqué » en rouge avec instruction `./start-dev`",
            'tech_lead' => "Plan technique\n\n- Étendre PipelineHealthService (BlockedWorker, stale running)\n- scripts/queue-worker.sh selon QUEUE_CONNECTION\n- TaskPipeline + StepOutputViewer : wire:poll conditionnel, gate-reviewed event\n- Tests PipelineHealthServiceTest\n- Fichiers : app/Services/PipelineHealthService.php, resources/views/livewire/task-pipeline.blade.php",
            'security' => "Revue sécurité — OK pour implémentation\n\n- Pas de fuite de tokens dans les messages d'erreur\n- Canaux broadcast : vérifier auth task.{id}\n- Pas d'exécution bash arbitraire côté UI\n- DevPipelineStepner : sandbox repo local uniquement",
        ];

        $runsByType = [];

        foreach ($completedAgents as $agentType => $output) {
            $runsByType[$agentType] = PipelineStep::create([
                'task_id' => $task->id,
                'role' => $agentType,
                'status' => PipelineStepStatus::Completed,
                'input' => [],
                'output' => $output,
                'model' => $models[$agentType] ?? 'claude-sonnet-4-6',
                'attempt' => 1,
                'started_at' => now()->subHours(2),
                'completed_at' => now()->subHours(2)->addMinutes(5),
            ]);
        }

        Gate::create([
            'task_id' => $task->id,
            'pipeline_step_id' => $runsByType['pm']->id,
            'gate_type' => GateType::SpecsReview,
            'status' => GateStatus::Approved,
            'reviewed_at' => now()->subHours(2)->addMinutes(10),
        ]);

        Gate::create([
            'task_id' => $task->id,
            'pipeline_step_id' => $runsByType['security']->id,
            'gate_type' => GateType::TechReview,
            'status' => GateStatus::Approved,
            'reviewed_at' => now()->subHour(),
        ]);
    }
}
