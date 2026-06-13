<?php

namespace Database\Seeders;

use App\Enums\AgentType;
use App\Enums\ProjectStatus;
use App\Enums\TaskMode;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        if (Project::query()->where('name', 'Maestro Demo')->exists()) {
            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'demo@maestro.local'],
            [
                'name' => 'Demo Maestro',
                'password' => Hash::make('password'),
            ],
        );

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Maestro Demo',
            'description' => 'Projet de démonstration pour tester les pipelines d\'agents Maestro en local.',
            'github_repo' => 'maestro-org/maestro-demo',
            'github_branch' => 'main',
            'context' => [
                'stack' => ['Laravel 13', 'Livewire 3', 'Tailwind CSS 4', 'PostgreSQL', 'Redis'],
                'conventions' => [
                    'Controllers dans app/Http/Controllers/{Domain}/',
                    'Form Requests pour la validation',
                    'Services pour la logique métier',
                ],
                'modules' => ['Projects', 'Tasks', 'Agents', 'Gates', 'Costs'],
                'design_system' => [
                    'Couleur primaire' => 'indigo',
                    'Composants' => 'Blade + Livewire + Alpine.js',
                ],
                'constraints' => [
                    'Authentification via Laravel Breeze',
                    'Temps réel via Soketi/Pusher',
                ],
            ],
            'pipeline_config' => DefaultPipelineSeeder::pipelines(),
            'gate_config' => DefaultPipelineSeeder::gateConfig(),
            'default_modes' => DefaultPipelineSeeder::defaultModes(),
            'model_config' => DefaultPipelineSeeder::defaultModels(),
            'status' => ProjectStatus::Active,
        ]);

        $sortOrder = 0;
        foreach (AgentType::cases() as $agentType) {
            ProjectAgent::create([
                'project_id' => $project->id,
                'agent_type' => $agentType,
                'is_active' => true,
                'model' => DefaultPipelineSeeder::defaultModels()[$agentType->value]
                    ?? config('maestro.default_models.'.$agentType->value),
                'system_prompt' => AgentPromptSeeder::for($agentType),
                'sort_order' => $sortOrder++,
            ]);
        }

        $tasks = [
            [
                'title' => 'Ajouter un filtre par module sur le board Kanban',
                'description' => 'Permettre de filtrer les tâches par module depuis la vue board. Le filtre doit persister en session et être réinitialisable.',
                'module' => 'Tasks',
                'type' => TaskType::Feature,
                'priority' => TaskPriority::High,
                'status' => TaskStatus::Backlog,
                'mode' => TaskMode::Manual,
                'sort_order' => 1,
            ],
            [
                'title' => 'Corriger l\'affichage du coût estimé à zéro',
                'description' => 'Quand estimated_cost est null, afficher « — » au lieu de « 0,00 € » sur la carte tâche.',
                'module' => 'Costs',
                'type' => TaskType::Bug,
                'priority' => TaskPriority::Medium,
                'status' => TaskStatus::InProgress,
                'mode' => TaskMode::SemiAuto,
                'current_agent' => AgentType::Dev,
                'sort_order' => 2,
            ],
            [
                'title' => 'Améliorer le feedback visuel pendant l\'exécution d\'un agent',
                'description' => 'Afficher un indicateur de progression et le nom de l\'agent en cours sur la page tâche, via les events WebSocket.',
                'module' => 'Agents',
                'type' => TaskType::Improvement,
                'priority' => TaskPriority::Low,
                'status' => TaskStatus::Backlog,
                'mode' => TaskMode::SemiAuto,
                'sort_order' => 3,
            ],
            [
                'title' => 'Mettre à jour les dépendances npm mineures',
                'description' => 'Bump des patch versions sans breaking change. Vérifier que npm run build passe.',
                'module' => null,
                'type' => TaskType::Chore,
                'priority' => TaskPriority::Low,
                'status' => TaskStatus::Backlog,
                'mode' => TaskMode::FullAuto,
                'sort_order' => 4,
            ],
        ];

        foreach ($tasks as $taskData) {
            Task::create(array_merge($taskData, ['project_id' => $project->id]));
        }
    }
}
