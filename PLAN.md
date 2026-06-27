# Plan Maestro v2 — Architecture Maestro + Hermes

Stack : Laravel + Livewire + Alpine + Tailwind (TALL).
Ne jamais suggérer Next.js, React, Vue ou tout autre SPA.
Toujours respecter la règle `.cursor/rules/no-database-wipe.mdc`.

---

## Nouvelle architecture

```
Telegram / CLI
      ↓
  Hermes Agent  (Hetzner — même serveur)
      ↓ MCP (JSON-RPC Bearer token)
  Maestro       (dashboard + tâches + gates + coûts)
      ↓ GitHub API
  Projets managés
```

**Maestro** = cockpit de pilotage : projets, kanban, tâches, gates d'approbation, suivi des coûts, agents de planning (PM, UX, Tech Lead…).

**Hermes** = exécution : l'agent qui code, lit les fichiers, fait tourner les commandes, utilise les modèles gratuits OpenRouter. Il se connecte à Maestro via MCP pour lire et mettre à jour les tâches.

---

## Chantier 1 — Serveur MCP dans Maestro

Exposer une API MCP (JSON-RPC 2.0 over HTTP) que Hermes utilise comme MCP server.

### 1.1 Token d'authentification MCP

**`database/migrations/..._create_mcp_tokens_table.php`**

```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('name');
$table->string('token', 64)->unique(); // SHA-256 stocké hashé
$table->timestamp('last_used_at')->nullable();
$table->timestamps();
```

**`app/Models/McpToken.php`** — modèle Eloquent standard.

**`app/Http/Controllers/Settings/McpTokenController.php`**
- `index()` — liste les tokens (sans afficher la valeur)
- `store()` — génère un nouveau token (retourne la valeur en clair une seule fois)
- `destroy()` — révoque un token

UI dans `resources/views/settings/index.blade.php` — section "Accès Hermes MCP" avec liste + bouton "Générer un token".

### 1.2 Middleware d'authentification MCP

**`app/Http/Middleware/AuthenticateMcpToken.php`**

Lit le header `Authorization: Bearer <token>`, cherche le hash dans `mcp_tokens`, charge l'utilisateur associé, met à jour `last_used_at`.

### 1.3 Contrôleur MCP

**`app/Http/Controllers/Mcp/McpController.php`**

Gère le protocole MCP (JSON-RPC 2.0) :
- `POST /api/mcp` — point d'entrée unique

Méthodes supportées :
- `initialize` — retourne les capacités du serveur
- `tools/list` — retourne la liste des tools disponibles
- `tools/call` — exécute un tool par son nom

### 1.4 Tools MCP

Chaque tool est une classe dans `app/Services/Mcp/Tools/` avec une méthode `execute(array $params, User $user): array`.

| Tool | Paramètres | Description |
|------|-----------|-------------|
| `list_projects` | — | Liste les projets actifs de l'utilisateur |
| `list_tasks` | `project_id`, `status?` | Liste les tâches (filtrables par statut) |
| `get_task` | `task_id` | Détail d'une tâche + outputs agents précédents (condensés) |
| `create_task` | `project_id`, `title`, `description`, `type`, `priority`, `module?` | Crée une tâche dans Maestro |
| `update_task_status` | `task_id`, `status` | Met à jour le statut d'une tâche |
| `add_agent_output` | `task_id`, `agent_type`, `output`, `model`, `input_tokens?`, `output_tokens?`, `cost?` | Enregistre l'output d'un agent Hermes (crée un `AgentRun`) |
| `request_gate` | `task_id`, `agent_run_id`, `gate_type` | Crée une gate d'approbation pour validation humaine |
| `log_cost` | `project_id`, `task_id?`, `model`, `input_tokens`, `output_tokens`, `cost` | Enregistre un coût dans `cost_logs` |

**`app/Services/Mcp/McpToolRegistry.php`** — registre central, instancie et route vers le bon tool.

### 1.5 Route

Dans `routes/api.php` :

```php
Route::post('/mcp', McpController::class)
    ->middleware(AuthenticateMcpToken::class)
    ->name('api.mcp');
```

### 1.6 Tests

**`tests/Feature/Mcp/McpServerTest.php`**
- `initialize` retourne les capacités
- `tools/list` retourne tous les tools
- Auth invalide → 401
- `create_task` crée bien la tâche en base
- `add_agent_output` crée un `AgentRun` et met à jour le coût
- `request_gate` crée la gate et broadcast l'événement

---

## Chantier 2 — Suppression du Dev agent local

Le Dev agent (clone repo, exécute du code localement, pousse la branche) est remplacé par Hermes. On retire le code d'exécution locale pour simplifier Maestro.

### Fichiers à supprimer

- `app/Services/DevAgentRunner.php`
- `app/Services/DevRunnerApi.php`
- `app/Services/CursorCloudRunner.php`
- `app/Services/CursorCloudClient.php`
- `app/Services/DevRepoTools.php`
- `app/Services/DevOutputStreamer.php`
- `app/Services/DevPromptBuilder.php`
- `app/Services/CursorRulesService.php`
- `app/Exceptions/DevAgentMaxAttemptsException.php`
- `app/Support/ProtectDevDatabase.php`

### Fichiers à modifier

**`app/Services/AgentCapabilities.php`**
- Retirer `isDev()`, `resolveDevRunner()`
- Garder : `resolveModel()`, `resolveSystemPrompt()`, `queue()`, `postAction()`

**`app/Jobs/RunAgentJob.php`**
- Retirer la branche `isDev` → toujours router vers `AgentRunnerService`

**`config/maestro.php`**
- Retirer les clés `dev_runner`, `cursor_*`, `dev_api_*`, `dev_claude_*`, `repos_path`

**`.env.example`**
- Retirer les variables Cursor et dev runner

### Agents conservés dans Maestro

Les agents de planning restent dans Maestro (ils n'ont pas besoin de GitHub ni de terminal) :

`pm`, `ux`, `tech_lead`, `security`, `qa`, `pr_expert`, `doc`, `discovery`

Le pipeline `feature` devient : `['pm', 'ux', 'tech_lead', 'security', 'qa', 'pr_expert', 'doc']`

L'agent `dev` est retiré du pipeline par défaut — Hermes le remplace via MCP après la gate `tech_lead`.

---

## Chantier 3 — Déploiement Hetzner

### 3.1 Maestro

Suivre `docs/DEPLOY.md`. Points clés :
- PHP 8.3, Node 20, Composer, Nginx, Supervisor, Redis
- `QUEUE_CONNECTION=redis` en production
- `BROADCAST_CONNECTION=pusher` ou Soketi pour le temps réel

### 3.2 Hermes sur le même serveur

```bash
curl -fsSL https://hermes-agent.nousresearch.com/install.sh | bash
hermes setup --portal
```

Configurer le MCP server Maestro dans Hermes :

```yaml
mcp_servers:
  maestro:
    url: https://maestro.ton-domaine.com/api/mcp
    auth:
      type: bearer
      token: "token_généré_dans_maestro_settings"
```

### 3.3 Modèles OpenRouter pour Hermes

Pour les tâches de dev (après specs Tech Lead) :
- `deepseek/deepseek-chat-v3-0324:free` — très bon en code, gratuit
- `meta-llama/llama-3.1-70b-instruct:free` — bon support tool use, gratuit

Pour les conversations Discovery et planning :
- Claude Sonnet via OpenRouter ou Anthropic directement

### 3.4 Telegram (optionnel)

Configurer le gateway Telegram de Hermes : `hermes gateway telegram`

---

## Chantier 4 — Sliding window Discovery chat

Le chat Discovery accumule l'historique sans limite.

**`app/Services/DiscoveryChatService.php`** — modifier `send()` :

```php
$maxHistory = (int) config('maestro.discovery_max_history', 10);
$trimmedHistory = array_slice($history, -$maxHistory);

$conversation = array_merge($trimmedHistory, [
    ['role' => 'user', 'content' => $enrichedMessage],
]);
```

**`config/maestro.php`** — ajouter :
```php
'discovery_max_history' => (int) env('MAESTRO_DISCOVERY_MAX_HISTORY', 10),
```

**`.env.example`** — ajouter :
```
MAESTRO_DISCOVERY_MAX_HISTORY=10
```

---

## Checklist

### Chantier 1 — MCP Server
- [ ] 1.1 Migration `mcp_tokens`
- [ ] 1.2 Modèle `McpToken`
- [ ] 1.3 Controller `McpTokenController` + UI Settings
- [ ] 1.4 Middleware `AuthenticateMcpToken`
- [ ] 1.5 Controller `McpController` (JSON-RPC router)
- [ ] 1.6 Tool `list_projects`
- [ ] 1.7 Tool `list_tasks`
- [ ] 1.8 Tool `get_task`
- [ ] 1.9 Tool `create_task`
- [ ] 1.10 Tool `update_task_status`
- [ ] 1.11 Tool `add_agent_output`
- [ ] 1.12 Tool `request_gate`
- [ ] 1.13 Tool `log_cost`
- [ ] 1.14 `McpToolRegistry`
- [ ] 1.15 Route `/api/mcp`
- [ ] 1.16 Tests `McpServerTest`

### Chantier 2 — Suppression Dev agent local
- [ ] 2.1 Supprimer les fichiers listés
- [ ] 2.2 Modifier `AgentCapabilities`
- [ ] 2.3 Modifier `RunAgentJob`
- [ ] 2.4 Nettoyer `config/maestro.php` et `.env.example`
- [ ] 2.5 Retirer `dev` du pipeline par défaut

### Chantier 3 — Déploiement
- [ ] 3.1 Déployer Maestro sur Hetzner
- [ ] 3.2 Installer et configurer Hermes
- [ ] 3.3 Générer un token MCP dans Maestro Settings
- [ ] 3.4 Configurer Hermes → MCP Maestro
- [ ] 3.5 Test bout en bout : créer une tâche via Hermes → visible dans Maestro
- [ ] 3.6 Configurer Telegram gateway (optionnel)

### Chantier 4 — Sliding window Discovery
- [ ] 4.1 Modifier `DiscoveryChatService::send()`
- [ ] 4.2 Ajouter config `discovery_max_history`
