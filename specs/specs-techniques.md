# Maestro — Specs Techniques

> Version 2.0 — 2026-06-11

---

## 1. Stack

| Couche | Technologie | Justification |
|--------|-------------|---------------|
| Backend | Laravel 11 | MVC robuste, queues, events, Auth natif |
| Frontend | Livewire 3 + Alpine.js + Tailwind CSS 3 | TALL Stack, réactivité sans SPA |
| Base de données | PostgreSQL 15 | JSON natif pour outputs agents, robuste |
| Queues | Laravel Horizon + Redis | Exécution async des agents |
| Temps réel | Laravel Echo + Pusher (ou Soketi self-hosted) | Statuts agents en direct |
| IA cerveau | Claude API (Anthropic) | PM, UX, Tech Lead, Security, Doc |
| IA code | Claude Code CLI | Dev Agent, QA Agent, PR Expert |
| GitHub | GitHub API v3 (REST) + GitHub OAuth | Repos, branches, PRs |
| Auth | Laravel Breeze (email + password) | Simple, pas besoin de social login en V1 |
| Hébergement | Hetzner CX21 (~€6/mois) | PHP-FPM + Nginx + PostgreSQL + Redis |
| Chiffrement | Laravel Crypt (AES-256-CBC) | Clés API utilisateur |

---

## 2. Architecture générale

```
┌─────────────────────────────────────────────────────┐
│                   MAESTRO APP                        │
│               (Laravel 11 + TALL)                    │
│                                                      │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │
│  │  Board   │  │ Projects │  │  Account/Settings │   │
│  │  Tasks   │  │ Context  │  │  API Key / Budget │   │
│  └──────────┘  └──────────┘  └──────────────────┘   │
│                                                      │
│  ┌──────────────────────────────────────────────┐    │
│  │            OrchestratorService               │    │
│  │  (résout quel agent, gate ou fin de pipeline)│    │
│  └──────────────────────┬─────────────────────┘    │
└─────────────────────────┼───────────────────────────┘
                          │ Jobs Redis (Horizon)
               ┌──────────┴──────────┐
               │                     │
       ┌───────▼──────┐      ┌───────▼──────┐
       │  Claude API  │      │ Claude Code  │
       │  (cerveau)   │      │    (code)    │
       │  PM/UX/TL    │      │ écrit, teste │
       │  Sec/Doc     │      │ valide, push │
       └───────┬──────┘      └───────┬──────┘
               └──────────┬──────────┘
                          │
                  ┌───────▼──────┐
                  │  GitHub API  │
                  │  branches    │
                  │  pull reqs   │
                  │  webhooks    │
                  └──────────────┘
```

---

## 3. Schéma de base de données

### `users`
```sql
id              bigint PK
uuid            uuid UNIQUE
name            string
email           string UNIQUE
password        string (bcrypt)
claude_api_key  text nullable        -- chiffré AES-256
monthly_budget  decimal(8,2) nullable -- budget alerte en euros
email_verified_at timestamp nullable
remember_token  string nullable
created_at, updated_at
```

### `projects`
```sql
id              bigint PK
uuid            uuid UNIQUE
user_id         bigint FK → users
name            string
description     text nullable
github_repo     string              -- "owner/repo"
github_branch   string default 'main'
github_token    text nullable       -- token OAuth GitHub, chiffré
context         jsonb               -- {stack, conventions, modules, design_system, constraints}
pipeline_config jsonb               -- {feature: [...agents], bug: [...agents], ...}
gate_config     jsonb               -- {feature: {gate_specs: true, gate_tech: true, gate_merge: true}, ...}
default_modes   jsonb               -- {feature: 'manual', bug: 'semi_auto', chore: 'full_auto', ...}
model_config    jsonb               -- {pm: 'claude-sonnet-4-6', security: 'claude-haiku-4-5', ...}
status          enum('active','archived') default 'active'
created_at, updated_at
```

### `project_roles`
```sql
id              bigint PK
project_id      bigint FK → projects
role      enum('pm','ux','tech_lead','security','dev','qa','pr_expert','doc')
is_active       boolean default true
model           string              -- claude-sonnet-4-6 | claude-haiku-4-5 | claude-opus-4-8
system_prompt   text                -- prompt système éditable, pré-rempli par défaut
sort_order      integer             -- ordre dans le pipeline
created_at, updated_at

UNIQUE(project_id, role)
```

### `tasks`
```sql
id              bigint PK
uuid            uuid UNIQUE
project_id      bigint FK → projects
title           string
description     text nullable
type            enum('feature','bug','improvement','chore')
priority        enum('critical','high','medium','low') default 'medium'
status          enum('backlog','in_progress','in_review','done','failed') default 'backlog'
mode            enum('manual','semi_auto','full_auto')
current_role   string nullable     -- role en cours d'exécution
github_branch   string nullable
github_pr_url   string nullable
github_pr_number integer nullable
pr_status       enum('none','open','draft','changes_requested','approved','merged','closed') default 'none'
estimated_cost  decimal(8,4) nullable
actual_cost     decimal(8,4) nullable
sort_order      integer default 0   -- pour le Kanban drag & drop
created_at, updated_at
```

### `pipeline_steps`
```sql
id              bigint PK
task_id         bigint FK → tasks
role      enum('pm','ux','tech_lead','security','dev','qa','pr_expert','doc')
status          enum('pending','running','completed','failed','waiting_gate','skipped')
input           jsonb               -- contexte complet envoyé à l'agent
output          text nullable       -- output brut
edited_output   text nullable       -- output édité par l'utilisateur avant gate
model           string
input_tokens    integer nullable
output_tokens   integer nullable
cached_tokens   integer nullable    -- tokens servis depuis le cache Anthropic
cost            decimal(8,6) nullable
attempt         integer default 1
error_message   text nullable
started_at      timestamp nullable
completed_at    timestamp nullable
created_at, updated_at
```

### `gates`
```sql
id              bigint PK
task_id         bigint FK → tasks
pipeline_step_id    bigint FK → pipeline_steps  -- agent qui attend la validation
gate_type       enum('specs_review','tech_review','merge_review')
status          enum('pending','approved','rejected')
feedback        text nullable       -- feedback si rejeté
regeneration_count integer default 0 -- nb de fois que l'agent a régénéré
reviewed_at     timestamp nullable
created_at, updated_at
```

### `cost_logs`
```sql
id              bigint PK
user_id         bigint FK → users
project_id      bigint FK → projects
task_id         bigint FK → tasks nullable
pipeline_step_id    bigint FK → pipeline_steps nullable
month           date                -- premier jour du mois (ex: 2026-06-01)
input_tokens    integer
output_tokens   integer
cached_tokens   integer
cost            decimal(8,6)
model           string
created_at
```

### `project_wizard_drafts`
```sql
id              bigint PK
user_id         bigint FK → users
step            integer default 1   -- étape en cours (1-4)
data            jsonb               -- données saisies
created_at, updated_at
```

---

## 4. Routes

### Auth
```
GET  /register          → RegisterController
POST /register
GET  /login             → LoginController
POST /login
POST /logout
```

### Account Settings
```
GET  /settings          → Settings\ProfileController@edit
PUT  /settings          → Settings\ProfileController@update
PUT  /settings/api-key  → Settings\ApiKeyController@update
PUT  /settings/budget   → Settings\BudgetController@update
```

### Projects — Wizard
```
GET  /projects/create           → ProjectWizardController@create  (étape 1)
POST /projects/create/step/1    → ProjectWizardController@storeStep1
POST /projects/create/step/2    → ProjectWizardController@storeStep2
POST /projects/create/step/3    → ProjectWizardController@storeStep3
POST /projects/create/step/4    → ProjectWizardController@storeStep4
POST /projects/create/finalize  → ProjectWizardController@finalize
```

### Projects — CRUD
```
GET  /projects                  → ProjectController@index
GET  /projects/{project}        → ProjectController@show  (board Kanban)
PUT  /projects/{project}        → ProjectController@update
DELETE /projects/{project}      → ProjectController@destroy

GET  /projects/{project}/settings           → ProjectSettingsController@edit
PUT  /projects/{project}/settings           → ProjectSettingsController@update
PUT  /projects/{project}/settings/roles    → ProjectRoleController@update
PUT  /projects/{project}/settings/pipeline  → ProjectPipelineController@update
POST /projects/{project}/settings/roles/{type}/test → ProjectRoleController@test
```

### Tasks
```
GET  /projects/{project}/tasks/create       → TaskController@create
POST /projects/{project}/tasks              → TaskController@store
GET  /projects/{project}/tasks/{task}       → TaskController@show
PUT  /projects/{project}/tasks/{task}       → TaskController@update
POST /projects/{project}/tasks/{task}/start → TaskController@start
POST /projects/{project}/tasks/{task}/retry → TaskController@retry
DELETE /projects/{project}/tasks/{task}     → TaskController@destroy

GET  /projects/{project}/tasks/{task}/estimate → CostEstimatorController@estimate
```

### Gates
```
POST /gates/{gate}/approve  → GateController@approve
POST /gates/{gate}/reject   → GateController@reject
PUT  /pipeline-steps/{step}/output → PipelineStepController@updateOutput  (édition inline)
```

### Coûts
```
GET /projects/{project}/costs → CostController@index
GET /costs                    → CostController@global  (tous projets)
```

### Webhooks (API publique)
```
POST /webhooks/github → GitHubWebhookController
```

### GitHub OAuth
```
GET  /auth/github           → GitHubOAuthController@redirect
GET  /auth/github/callback  → GitHubOAuthController@callback
```

---

## 5. Services

### `OrchestratorService`

Service central — décide quel agent lancer, si un gate est requis, ou si le pipeline est terminé.

```php
class OrchestratorService
{
    public function advance(Task $task): void
    {
        $nextAgent = $this->resolveNextRole($task);

        if (!$nextAgent) {
            $task->update(['status' => 'done']);
            broadcast(new TaskCompleted($task));
            return;
        }

        if ($this->requiresGate($task, $nextAgent)) {
            $lastRun = $task->pipelineSteps()->latest()->first();
            $gate = Gate::create([
                'task_id'      => $task->id,
                'pipeline_step_id' => $lastRun->id,
                'gate_type'    => $this->resolveGateType($nextAgent),
                'status'       => 'pending',
            ]);
            broadcast(new GatePending($gate));
            app(NotificationService::class)->notifyGate($task, $gate);
            return;
        }

        dispatch(new RunPipelineStepJob($task, $nextAgent));
    }

    private function resolveNextRole(Task $task): ?string
    {
        $pipeline = $this->getPipelineForTask($task);
        $completed = $task->pipelineSteps()
            ->whereIn('status', ['completed', 'skipped'])
            ->pluck('role')
            ->toArray();

        foreach ($pipeline as $agent) {
            if (!in_array($agent, $completed)) {
                return $agent;
            }
        }
        return null;
    }

    public function getPipelineForTask(Task $task): array
    {
        $config = $task->project->pipeline_config;
        return $config[$task->type] ?? $this->defaultPipeline($task->type);
    }

    private function requiresGate(Task $task, string $nextAgent): bool
    {
        if ($task->mode === 'full_auto') return false;

        $gateConfig = $task->project->gate_config[$task->type] ?? [];

        return match($nextAgent) {
            'ux', 'tech_lead' => ($task->mode === 'manual') && ($gateConfig['gate_specs'] ?? true),
            'security'        => ($task->mode === 'manual') && ($gateConfig['gate_tech'] ?? true),
            'doc'             => $gateConfig['gate_merge'] ?? true,
            default           => false,
        };
    }

    private function resolveGateType(string $nextAgent): string
    {
        return match($nextAgent) {
            'ux', 'tech_lead' => 'specs_review',
            'security'        => 'tech_review',
            'doc'             => 'merge_review',
            default           => 'specs_review',
        };
    }

    private function defaultPipeline(string $type): array
    {
        return match($type) {
            'feature'     => ['pm', 'ux', 'tech_lead', 'security', 'dev', 'qa', 'pr_expert', 'doc'],
            'bug'         => ['tech_lead', 'security', 'dev', 'qa', 'pr_expert', 'doc'],
            'improvement' => ['pm', 'tech_lead', 'security', 'dev', 'qa', 'pr_expert', 'doc'],
            'chore'       => ['tech_lead', 'dev', 'pr_expert'],
            default       => ['pm', 'dev', 'pr_expert'],
        };
    }
}
```

---

### `PipelineStepRunnerService`

Exécute un agent via la Claude API avec prompt caching.

```php
class PipelineStepRunnerService
{
    public function run(PipelineStep $run): PipelineStepResult
    {
        $project = $run->task->project;
        $apiKey  = decrypt($project->user->claude_api_key);
        $agent   = RunnerFactory::make($run->role, $project);
        $model   = $project->model_config[$run->role] ?? 'claude-sonnet-4-6';

        $client = Anthropic::factory()->withApiKey($apiKey)->make();

        $response = $client->messages()->create([
            'model'      => $model,
            'max_tokens' => 4096,
            'system'     => [
                // Contexte projet mis en cache — payé une seule fois par fenêtre de 5 min
                [
                    'type'          => 'text',
                    'text'          => $this->buildProjectContext($project),
                    'cache_control' => ['type' => 'ephemeral'],
                ],
                // Prompt spécifique à l'agent
                [
                    'type' => 'text',
                    'text' => $agent->systemPrompt(),
                ],
            ],
            'messages' => [
                ['role' => 'user', 'content' => $agent->buildPrompt($run)],
            ],
        ]);

        $usage = $response->usage;
        $cost  = $this->calculateCost($model, $usage);

        // Log du coût
        CostLog::create([
            'user_id'       => $project->user_id,
            'project_id'    => $project->id,
            'task_id'       => $run->task_id,
            'pipeline_step_id'  => $run->id,
            'month'         => now()->startOfMonth(),
            'input_tokens'  => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'cached_tokens' => $usage->cacheReadInputTokens ?? 0,
            'cost'          => $cost,
            'model'         => $model,
        ]);

        return new PipelineStepResult(
            output:       $response->content[0]->text,
            inputTokens:  $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cachedTokens: $usage->cacheReadInputTokens ?? 0,
            cost:         $cost,
        );
    }

    private function buildProjectContext(Project $project): string
    {
        $ctx = $project->context;
        return <<<TEXT
        ## Contexte du projet : {$project->name}

        ### Stack technique
        {$ctx['stack']}

        ### Conventions de code
        {$ctx['conventions']}

        ### Modules existants
        {$ctx['modules']}

        ### Design system
        {$ctx['design_system']}

        ### Contraintes absolues
        {$ctx['constraints']}
        TEXT;
    }

    private function calculateCost(string $model, object $usage): float
    {
        $prices = [
            'claude-sonnet-4-6' => ['input' => 0.000003,   'output' => 0.000015,   'cache' => 0.0000003],
            'claude-haiku-4-5'  => ['input' => 0.00000025, 'output' => 0.00000125, 'cache' => 0.000000025],
            'claude-opus-4-8'   => ['input' => 0.000015,   'output' => 0.000075,   'cache' => 0.0000015],
        ];
        $p = $prices[$model] ?? $prices['claude-sonnet-4-6'];

        return ($usage->inputTokens * $p['input'])
             + ($usage->outputTokens * $p['output'])
             + (($usage->cacheReadInputTokens ?? 0) * $p['cache']);
    }
}
```

---

### `RunPipelineStepJob`

Job Horizon qui orchestre l'exécution d'un agent et relance l'orchestrateur.

```php
class RunPipelineStepJob implements ShouldQueue
{
    public string $queue = 'roles';
    public int $timeout  = 120;

    public function __construct(
        public readonly Task   $task,
        public readonly string $agentType,
    ) {}

    public function handle(PipelineStepRunnerService $runner, OrchestratorService $orchestrator): void
    {
        $run = PipelineStep::create([
            'task_id'    => $this->task->id,
            'role' => $this->agentType,
            'status'     => 'running',
            'input'      => $this->buildInput(),
            'model'      => $this->task->project->model_config[$this->agentType] ?? 'claude-sonnet-4-6',
        ]);

        $this->task->update(['current_role' => $this->agentType]);
        broadcast(new PipelineStepUpdated($run));

        try {
            // Dev Agent a son propre runner (Claude Code CLI)
            $result = $this->agentType === 'dev'
                ? app(DevPipelineStepner::class)->run($run)
                : $runner->run($run);

            $run->update([
                'status'        => 'completed',
                'output'        => $result->output,
                'input_tokens'  => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'cached_tokens' => $result->cachedTokens,
                'cost'          => $result->cost,
                'completed_at'  => now(),
            ]);

            $this->task->increment('actual_cost', $result->cost);
            broadcast(new PipelineStepUpdated($run->fresh()));
            $orchestrator->advance($this->task->fresh());

        } catch (Throwable $e) {
            if ($run->attempt < 3) {
                $run->increment('attempt');
                $this->release(30);
            } else {
                $run->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $this->task->update(['status' => 'failed', 'current_role' => null]);
                broadcast(new PipelineStepUpdated($run));
                app(NotificationService::class)->notifyFailure($this->task, $run);
            }
        }
    }

    private function buildInput(): array
    {
        // Agrège tous les outputs (édités si disponibles) des agents précédents
        return $this->task->pipelineSteps()
            ->where('status', 'completed')
            ->get()
            ->mapWithKeys(fn($r) => [
                $r->role => $r->edited_output ?? $r->output
            ])
            ->toArray();
    }
}
```

---

### `DevPipelineStepner`

Exécute Claude Code CLI sur le serveur, avec boucle de validation.

```php
class DevPipelineStepner
{
    public string $queue = 'REMOVED_DEV_AGENT';

    public function run(PipelineStep $run): PipelineStepResult
    {
        $project  = $run->task->project;
        $repoPath = $this->cloneOrPull($project);
        $branch   = "feature/{$run->task->uuid}-" . str($run->task->title)->slug();

        app(GitHubService::class)->createBranch($project, $branch);
        $this->checkoutBranch($repoPath, $branch);

        // Première génération
        $this->runClaudeCode($repoPath, $this->buildDevPrompt($run));

        // Boucle de validation (max 3 tentatives)
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $validation = $this->validate($repoPath);

            if ($validation->passes()) {
                $this->pushBranch($repoPath, $branch);
                $prDescription = $this->buildPrDescription($run, $validation);
                $pr = app(GitHubService::class)->openPullRequest($run->task, $branch, $prDescription);

                return new PipelineStepResult(
                    output:  "Branche : `{$branch}`\nPR : {$pr['html_url']}\nTests : {$validation->summary()}",
                    prUrl:   $pr['html_url'],
                    prBranch: $branch,
                );
            }

            if ($attempt < 3) {
                // Donne les erreurs à Claude Code pour qu'il corrige
                $this->runClaudeCode(
                    $repoPath,
                    "Les validations ont échoué :\n{$validation->errorsAsString()}\n\nCorrige le code pour que tout passe."
                );
            }
        }

        throw new DevAgentMaxAttemptsException(
            "Échec après 3 tentatives.\n\n" . $validation->errorsAsString()
        );
    }

    private function validate(string $repoPath): ValidationResult
    {
        $errors = [];

        $artisan = Process::path($repoPath)->run(['php', 'artisan', '--version']);
        if ($artisan->failed()) $errors['php'] = $artisan->errorOutput();

        $pest = Process::path($repoPath)->run(['./vendor/bin/pest', '--no-coverage', '--compact']);
        if ($pest->failed()) $errors['tests'] = $pest->output();

        $npm = Process::path($repoPath)->run(['npm', 'run', 'build']);
        if ($npm->failed()) $errors['frontend'] = $npm->errorOutput();

        return new ValidationResult(empty($errors), $errors);
    }

    private function runClaudeCode(string $repoPath, string $prompt): void
    {
        Process::path($repoPath)
            ->timeout(180)
            ->run(['claude', '--print', '--dangerously-skip-permissions', $prompt]);
    }

    private function cloneOrPull(Project $project): string
    {
        $path = config('maestro.repos_path') . '/' . str($project->github_repo)->replace('/', '_');

        if (!is_dir($path)) {
            $token = decrypt($project->github_token);
            $url   = "https://{$token}@github.com/{$project->github_repo}.git";
            Process::run(['git', 'clone', $url, $path]);
        } else {
            Process::path($path)->run(['git', 'pull', 'origin', $project->github_branch]);
        }

        return $path;
    }
}
```

---

### `GitHubService`

```php
class GitHubService
{
    private function client(Project $project): Client
    {
        return new Client(new AuthTokenPlugin(decrypt($project->github_token)));
    }

    public function createBranch(Project $project, string $branchName): void
    {
        $sha = $this->getDefaultBranchSha($project);
        $this->client($project)->git()->references()->create($project->github_repo, [
            'ref' => "refs/heads/{$branchName}",
            'sha' => $sha,
        ]);
    }

    public function openPullRequest(Task $task, string $branch, string $description): array
    {
        $pr = $this->client($task->project)->pullRequests()->create($task->project->github_repo, [
            'title' => "[{$task->type}] {$task->title}",
            'body'  => $description,
            'head'  => $branch,
            'base'  => $task->project->github_branch,
        ]);

        $task->update([
            'github_branch'   => $branch,
            'github_pr_url'   => $pr['html_url'],
            'github_pr_number'=> $pr['number'],
            'pr_status'       => 'open',
            'status'          => 'in_review',
        ]);

        return $pr;
    }

    public function syncPrStatus(Task $task): void
    {
        $pr     = $this->client($task->project)->pullRequests()->show(
            $task->project->github_repo,
            $task->github_pr_number
        );
        $status = $pr['merged'] ? 'merged' : ($pr['state'] === 'closed' ? 'closed' : 'open');

        $task->update(['pr_status' => $status]);

        if ($status === 'merged') {
            $task->update(['status' => 'done']);
            dispatch(new RunPipelineStepJob($task, 'doc'));
        }
    }

    private function getDefaultBranchSha(Project $project): string
    {
        $ref = $this->client($project)->git()->references()->show(
            $project->github_repo,
            "heads/{$project->github_branch}"
        );
        return $ref['object']['sha'];
    }
}
```

### `CostEstimatorService`

```php
class CostEstimatorService
{
    // Tailles moyennes observées par agent (tokens)
    const AVG_OUTPUT_TOKENS = [
        'pm'         => 800,
        'ux'         => 600,
        'tech_lead'  => 1000,
        'security'   => 300,
        'dev'        => 3000,
        'qa'         => 800,
        'pr_expert'  => 500,
        'doc'        => 300,
    ];

    // Taille du contexte projet (constant, mis en cache après le 1er appel)
    const PROJECT_CONTEXT_TOKENS = 2000;

    public function estimate(Task $task): array
    {
        $pipeline  = app(OrchestratorService::class)->getPipelineForTask($task);
        $estimates = [];
        $total     = 0;

        foreach ($pipeline as $index => $agent) {
            $model       = $task->project->model_config[$agent] ?? 'claude-sonnet-4-6';
            $inputTokens = self::PROJECT_CONTEXT_TOKENS
                         + $this->estimateAccumulatedContext($task, $index)
                         + 500; // prompt tâche
            $outputTokens = self::AVG_OUTPUT_TOKENS[$agent] ?? 500;

            // Après le 1er agent, le contexte projet est en cache (90% moins cher)
            $cachedContextCost = $index > 0
                ? self::PROJECT_CONTEXT_TOKENS * $this->price($model, 'cache')
                : self::PROJECT_CONTEXT_TOKENS * $this->price($model, 'input');

            $cost = $cachedContextCost
                  + (($inputTokens - self::PROJECT_CONTEXT_TOKENS) * $this->price($model, 'input'))
                  + ($outputTokens * $this->price($model, 'output'));

            $estimates[$agent] = [
                'model'          => $model,
                'input_tokens'   => $inputTokens,
                'output_tokens'  => $outputTokens,
                'estimated_cost' => round($cost, 5),
            ];
            $total += $cost;
        }

        return [
            'roles'       => $estimates,
            'total_low'    => round($total * 0.8, 4),
            'total_high'   => round($total * 1.5, 4),
            'total_mid'    => round($total, 4),
            'caching_note' => 'Prompt caching actif — économie estimée ~70% sur le contexte projet',
        ];
    }

    private function price(string $model, string $type): float
    {
        return match([$model, $type]) {
            ['claude-sonnet-4-6', 'input']  => 0.000003,
            ['claude-sonnet-4-6', 'output'] => 0.000015,
            ['claude-sonnet-4-6', 'cache']  => 0.0000003,
            ['claude-haiku-4-5',  'input']  => 0.00000025,
            ['claude-haiku-4-5',  'output'] => 0.00000125,
            ['claude-haiku-4-5',  'cache']  => 0.000000025,
            default                         => 0.000003,
        };
    }

    private function estimateAccumulatedContext(Task $task, int $agentIndex): int
    {
        // Chaque agent précédent contribue ~500 tokens de contexte supplémentaire
        return $agentIndex * 500;
    }
}
```

---

### `GateController`

```php
class GateController extends Controller
{
    public function approve(Request $request, Gate $gate): RedirectResponse
    {
        $gate->update(['status' => 'approved', 'reviewed_at' => now()]);

        // Utiliser l'output édité si disponible
        if ($request->filled('edited_output')) {
            $gate->agentRun->update(['edited_output' => $request->edited_output]);
        }

        app(OrchestratorService::class)->advance($gate->task->fresh());

        return back()->with('success', 'Gate validée — pipeline relancé.');
    }

    public function reject(Request $request, Gate $gate): RedirectResponse
    {
        $request->validate(['feedback' => 'required|string|min:10']);

        if ($gate->regeneration_count >= 2) {
            $gate->update(['status' => 'rejected']);
            $gate->task->update(['status' => 'failed']);
            return back()->with('error', 'Maximum de régénérations atteint. Intervention manuelle requise.');
        }

        $gate->update([
            'status'              => 'pending',
            'feedback'            => $request->feedback,
            'regeneration_count'  => $gate->regeneration_count + 1,
        ]);

        // Relance l'agent avec le feedback
        dispatch(new RunPipelineStepJob(
            $gate->task,
            $gate->agentRun->role,
            feedback: $request->feedback
        ));

        return back()->with('success', 'Feedback envoyé — l\'agent régénère.');
    }
}
```

---

### `ProjectWizardController`

```php
class ProjectWizardController extends Controller
{
    public function create(): View
    {
        $draft = ProjectWizardDraft::firstOrCreate(['user_id' => auth()->id()]);
        return view('projects.wizard', compact('draft'));
    }

    public function storeStep1(StoreWizardStep1Request $request): JsonResponse
    {
        $draft = ProjectWizardDraft::updateOrCreate(
            ['user_id' => auth()->id()],
            ['step' => 2, 'data->step1' => $request->validated()]
        );

        // Si "Lire le contexte depuis le repo", on fetch README + CLAUDE.md via GitHub API
        if ($request->read_context_from_repo) {
            $context = app(GitHubContextReader::class)->read(
                $request->github_repo,
                $request->github_token
            );
            $draft->update(['data->prefilled_context' => $context]);
        }

        return response()->json(['step' => 2, 'prefilled_context' => $draft->data['prefilled_context'] ?? null]);
    }

    // Steps 2, 3, 4 suivent le même pattern

    public function finalize(): RedirectResponse
    {
        $draft = ProjectWizardDraft::where('user_id', auth()->id())->firstOrFail();
        $data  = $draft->data;

        $project = Project::create([
            'user_id'        => auth()->id(),
            'name'           => $data['step1']['name'],
            'github_repo'    => $data['step1']['github_repo'],
            'github_branch'  => $data['step1']['github_branch'],
            'github_token'   => encrypt($data['step1']['github_token']),
            'context'        => $data['step2'],
            'pipeline_config'=> $data['step3']['pipeline'],
            'gate_config'    => $data['step3']['gates'],
            'default_modes'  => $data['step3']['modes'],
            'model_config'   => $data['step4']['models'],
        ]);

        // Créer les project_roles avec les prompts personnalisés
        foreach ($data['step4']['roles'] as $type => $config) {
            ProjectRole::create([
                'project_id'    => $project->id,
                'role'    => $type,
                'is_active'     => $config['is_active'],
                'model'         => $config['model'],
                'system_prompt' => $config['system_prompt'],
                'sort_order'    => $config['sort_order'],
            ]);
        }

        $draft->delete();

        return redirect()->route('projects.show', $project)->with('success', 'Projet créé !');
    }
}
```

---

## 6. Temps réel — Events & Broadcasts

```php
// Events diffusés sur le channel "task.{id}"

class PipelineStepUpdated implements ShouldBroadcast
{
    public function broadcastOn(): Channel
    {
        return new Channel("task.{$this->run->task_id}");
    }

    public function broadcastWith(): array
    {
        return [
            'run_id'     => $this->run->id,
            'role' => $this->run->role,
            'status'     => $this->run->status,
            'cost'       => $this->run->cost,
            'output'     => $this->run->status === 'completed' ? $this->run->output : null,
        ];
    }
}

class GatePending implements ShouldBroadcast
{
    public function broadcastOn(): Channel
    {
        return new Channel("task.{$this->gate->task_id}");
    }
}

class TaskCompleted implements ShouldBroadcast
{
    public function broadcastOn(): Channel
    {
        return new Channel("task.{$this->task->id}");
    }
}
```

Composant Livewire `TaskPipeline` — écoute les broadcasts et se rafraîchit :

```php
class TaskPipeline extends Component
{
    public Task $task;

    protected function getListeners(): array
    {
        return [
            "echo:task.{$this->task->id},PipelineStepUpdated" => '$refresh',
            "echo:task.{$this->task->id},GatePending"     => '$refresh',
            "echo:task.{$this->task->id},TaskCompleted"   => '$refresh',
        ];
    }

    public function render(): View
    {
        return view('livewire.task-pipeline', [
            'runs'         => $this->task->pipelineSteps()->orderBy('created_at')->get(),
            'pendingGates' => $this->task->gates()->where('status', 'pending')->get(),
        ]);
    }
}
```

---

## 7. Webhook GitHub

```php
// routes/api.php
Route::post('/webhooks/github', GitHubWebhookController::class)
     ->middleware('github.webhook');

// Middleware de vérification HMAC-SHA256
class VerifyGitHubWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Hub-Signature-256');
        $payload   = $request->getContent();
        $secret    = config('services.github.webhook_secret');
        $expected  = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        abort_unless(hash_equals($expected, $signature ?? ''), 401);

        return $next($request);
    }
}

class GitHubWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if ($request->input('action') === 'closed' &&
            $request->boolean('pull_request.merged')) {
            $task = Task::where('github_pr_number', $request->input('pull_request.number'))
                        ->first();
            if ($task) {
                app(GitHubService::class)->syncPrStatus($task);
            }
        }
        return response()->noContent();
    }
}
```

---

## 8. Gestion des clés API

```php
// Dans User model
public function setClaudeApiKeyAttribute(string $value): void
{
    $this->attributes['claude_api_key'] = encrypt($value);
}

public function getClaudeApiKeyAttribute(string $value): string
{
    return decrypt($value);
}

// Validation de la clé au moment de l'enregistrement
class ApiKeyController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate(['claude_api_key' => 'required|string|min:10']);

        // Ping l'API Anthropic pour valider
        try {
            $client = Anthropic::factory()
                ->withApiKey($request->claude_api_key)
                ->make();
            $client->messages()->create([
                'model'      => 'claude-haiku-4-5',
                'max_tokens' => 1,
                'messages'   => [['role' => 'user', 'content' => 'ping']],
            ]);
        } catch (ApiException $e) {
            return back()->withErrors(['claude_api_key' => 'Clé API invalide ou quota dépassé.']);
        }

        auth()->user()->update(['claude_api_key' => $request->claude_api_key]);
        return back()->with('success', 'Clé API enregistrée ✓');
    }
}
```

---

## 9. RunnerFactory — Prompts par défaut

```php
class RunnerFactory
{
    public static function make(string $type, Project $project): BaseRunner
    {
        // Cherche d'abord le prompt personnalisé dans project_roles
        $customAgent = $project->roles()->where('role', $type)->first();
        $systemPrompt = $customAgent?->system_prompt ?? self::defaultPrompt($type);

        return match($type) {
            'pm'         => new PmAgent($systemPrompt),
            'ux'         => new UxAgent($systemPrompt),
            'tech_lead'  => new TechLeadAgent($systemPrompt),
            'security'   => new SecurityAgent($systemPrompt),
            'dev'        => new DevAgent($systemPrompt),
            'qa'         => new QaAgent($systemPrompt),
            'pr_expert'  => new PrExpertAgent($systemPrompt),
            'doc'        => new DocAgent($systemPrompt),
            default      => throw new InvalidArgumentException("Agent inconnu : {$type}"),
        };
    }
}
```

Chaque Agent implémente :
- `systemPrompt(): string` — prompt système (rôle, objectif, format de sortie attendu)
- `buildPrompt(PipelineStep $run): string` — construit le prompt utilisateur avec le contexte de la tâche et les outputs des agents précédents

---

## 10. Infrastructure

### Serveur unique (MVP)

Hetzner CX21 — €5.83/mois :
- Ubuntu 22.04
- PHP 8.3 + FPM + Nginx
- PostgreSQL 15
- Redis 7
- Node.js 20 (pour `npm run build`)
- Claude Code CLI installé globalement (`npm install -g @anthropic-ai/claude-code`)
- Supervisor pour Horizon

Repos GitHub clonés dans `/srv/maestro-repos/`

### Variables d'environnement

```env
APP_KEY=...
APP_URL=https://maestro.yourdomain.com

DB_CONNECTION=pgsql
DB_DATABASE=maestro
DB_USERNAME=maestro
DB_PASSWORD=...

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

BROADCAST_DRIVER=pusher  # ou 'log' pour dev local
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...

GITHUB_CLIENT_ID=...      # OAuth app GitHub
GITHUB_CLIENT_SECRET=...
GITHUB_WEBHOOK_SECRET=...

MAESTRO_REPOS_PATH=/srv/maestro-repos

# Note : la clé API Claude est stockée par utilisateur en base (chiffrée)
# Pas de clé globale — chaque utilisateur utilise la sienne
```

### Horizon — Configuration des queues

```php
'environments' => [
    'production' => [
        'roles' => [
            'connection' => 'redis',
            'queue'      => ['roles'],
            'balance'    => 'auto',
            'processes'  => 5,
            'tries'      => 1,      // retry géré dans le job
            'timeout'    => 120,    // 2 min max (Claude API)
        ],
        'REMOVED_DEV_AGENT' => [
            'connection' => 'redis',
            'queue'      => ['REMOVED_DEV_AGENT'],
            'balance'    => 'simple',
            'processes'  => 2,
            'tries'      => 1,
            'timeout'    => 300,    // 5 min (Claude Code + tests)
        ],
    ],
],
```

---

## 11. Sécurité

- Clés API (Claude, GitHub) chiffrées en base avec `encrypt()` Laravel (AES-256-CBC)
- Webhook GitHub vérifié via HMAC-SHA256
- Repos clonés dans `/srv/maestro-repos/`, non accessibles via le web
- Authentification : Laravel Breeze (sessions, CSRF)
- Politique d'accès : un utilisateur ne peut accéder qu'à ses propres projets/tâches (policy sur chaque ressource)
- Rate limiting sur les routes API et webhooks
- Pas de clé API globale : chaque utilisateur utilise la sienne → isolation totale

---

## 12. Structure des dossiers

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Projects/
│   │   │   ├── ProjectController.php
│   │   │   ├── ProjectWizardController.php
│   │   │   ├── ProjectSettingsController.php
│   │   │   └── ProjectRoleController.php
│   │   ├── Tasks/
│   │   │   ├── TaskController.php
│   │   │   └── CostEstimatorController.php
│   │   ├── Gates/
│   │   │   └── GateController.php
│   │   ├── Settings/
│   │   │   ├── ProfileController.php
│   │   │   └── ApiKeyController.php
│   │   └── Webhooks/
│   │       └── GitHubWebhookController.php
│   ├── Middleware/
│   │   └── VerifyGitHubWebhook.php
│   └── Requests/  (FormRequest pour chaque action)
│
├── Models/
│   ├── User.php
│   ├── Project.php
│   ├── ProjectRole.php
│   ├── Task.php
│   ├── PipelineStep.php
│   ├── Gate.php
│   ├── CostLog.php
│   └── ProjectWizardDraft.php
│
├── Services/
│   ├── OrchestratorService.php
│   ├── PipelineStepRunnerService.php
│   ├── DevPipelineStepner.php
│   ├── GitHubService.php
│   ├── CostEstimatorService.php
│   ├── NotificationService.php
│   └── GitHubContextReader.php
│
├── Agents/
│   ├── BaseRunner.php
│   ├── RunnerFactory.php
│   ├── PmAgent.php
│   ├── UxAgent.php
│   ├── TechLeadAgent.php
│   ├── SecurityAgent.php
│   ├── DevAgent.php
│   ├── QaAgent.php
│   ├── PrExpertAgent.php
│   └── DocAgent.php
│
├── Jobs/
│   └── RunPipelineStepJob.php
│
├── Events/
│   ├── PipelineStepUpdated.php
│   ├── GatePending.php
│   └── TaskCompleted.php
│
└── Livewire/
    ├── TaskPipeline.php
    ├── StepOutputViewer.php
    ├── CostEstimationPanel.php
    └── ProjectWizard.php
```
