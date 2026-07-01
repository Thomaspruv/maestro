# {{APP_NAME}} — Serveur MCP

Guide d'intégration pour **Hermes** et **Claude** (Code / Cowork).

---

## 1. Connexion

| Paramètre | Valeur |
|-----------|--------|
| **URL** | `{{MCP_URL}}` |
| **Protocole** | JSON-RPC 2.0, POST HTTP |
| **Headers** | `Content-Type: application/json` |
| **Auth** | `Authorization: Bearer <TOKEN>` |

**Où trouver l'URL exacte :** page publique **Paramètres → Intégrations MCP → Documentation API** (`/settings/mcp/docs`)

> Utilisez toujours l'URL affichée dans l'interface Maestro, pas une URL devinée.

---

## 2. Obtenir un token

1. Maestro → **Paramètres → Intégrations MCP**
2. Nom : `Hermes prod` ou `Claude Code`
3. **Générer un token** → copier immédiatement (40 caractères, affiché une seule fois)

**Règles :**

- Token = valeur seule, **sans** le préfixe `Bearer `
- Hermes ajoute `Bearer` automatiquement dans les requêtes
- Claude Code : `--header "Authorization: Bearer TOKEN"`

### Test rapide

```bash
curl -s -X POST {{MCP_URL}} \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

Réponse OK (HTTP 200) :

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "protocolVersion": "2024-11-05",
    "capabilities": { "tools": {} },
    "serverInfo": { "name": "maestro", "version": "2.0.0" }
  }
}
```

Lister les tools :

```bash
curl -s -X POST {{MCP_URL}} \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
```

Dans Maestro → Paramètres MCP, la colonne **Dernière utilisation** du token doit se mettre à jour après un appel réussi.

---

## 3. Configuration Hermes

```yaml
mcp_servers:
  maestro:
    url: {{MCP_URL}}
    auth:
      type: bearer
      token: "VOTRE_TOKEN_40_CARACTERES"
```

**Important :**

- Collez **uniquement** la valeur du token, sans le préfixe `Bearer `
- Ne mettez pas d'espaces ou de retours ligne dans le token
- Vérifiez que Maestro est accessible depuis le serveur Hermes (même port)

### Instructions système — cron Hermes

À coller dans la routine planifiée (toutes les 5 min) :

```
Tu es connecté au serveur MCP Maestro (workflow_mode: hermes_only).

1. Appelle list_hermes_tasks() pour voir les tâches prêtes.
2. Si count = 0, arrête-toi.
3. Prends tasks[0] (priorité la plus haute).
4. Appelle claim_hermes_task(task_id) pour réserver la tâche.
5. Appelle get_task(task_id) et lis hermes.specs_preview (titre, description, module).
6. Clone le dépôt GitHub indiqué (hermes.github.repo, branche hermes.github.branch).
7. Implémente le code selon le titre et la description de la tâche.
8. Appelle record_step_output avec :
   - task_id
   - role: "dev"
   - output: résumé de ce qui a été fait (fichiers, décisions, commandes)
   - model: le modèle utilisé
   - cost: 0 si gratuit
9. La tâche passe automatiquement en statut done.

En cas d'échec avant l'étape 8 :
  update_task_status(task_id, "waiting_hermes")
```

Fréquence suggérée : `*/5 * * * *`

---

## 4. Configuration Claude Code

```bash
claude mcp add --transport http maestro {{MCP_URL}} \
  --header "Authorization: Bearer VOTRE_TOKEN"
```

Vérification :

```bash
claude mcp list
```

Exemples de commandes dans Claude Code :

- « Liste mes projets Maestro » → `list_projects`
- « Quelles tâches attendent Hermes ? » → `list_hermes_tasks`
- « Crée une tâche feature haute priorité dans le projet X » → `create_task`

---

## 5. Configuration Claude Cowork (OAuth)

Cowork n'utilise **pas** de token statique.

1. Cowork → **Paramètres → Connecteurs → Ajouter un connecteur custom**
2. URL : `{{MCP_URL}}`
3. Se connecter à Maestro dans le navigateur et approuver l'accès

Prérequis côté Maestro :

- `APP_URL` doit être l'URL HTTPS exacte (ex. `https://maestro.votre-domaine.com`)
- Routes OAuth accessibles :
  - `/.well-known/oauth-protected-resource`
  - `/.well-known/oauth-authorization-server`

Test :

```bash
curl -s {{APP_URL}}/.well-known/oauth-protected-resource
```

---

## 6. Protocole MCP

### Méthodes supportées

| Méthode | Description |
|---------|-------------|
| `initialize` | Handshake, retourne les capacités du serveur |
| `tools/list` | Liste les 13 tools disponibles |
| `tools/call` | Exécute un tool par son nom |

### Format `tools/call`

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "list_hermes_tasks",
    "arguments": {}
  }
}
```

La réponse utile est dans **`result.content[0].text`** (string JSON à parser).

### Codes d'erreur JSON-RPC

| Code HTTP | Code JSON-RPC | Signification |
|-----------|---------------|---------------|
| 400 | -32600 | Requête invalide (`jsonrpc` absent ou incorrect) |
| 401 | -32001 | Token manquant ou invalide |
| 404 | -32601 | Méthode ou tool inconnu |
| 422 | -32602 | Paramètres manquants ou invalides |

---

## 7. Tools — exemples

> La référence complète des schémas (`inputSchema`) est générée automatiquement depuis le code (section 12 ci-dessous ou page Documentation API).

| Tool | Description |
|------|-------------|
| `list_projects` | Projets actifs de l'utilisateur |
| `list_tasks` | Tâches d'un projet (`project_id`, filtres `status` ou `kanban_column` optionnels) |
| `list_kanban_board` | **Kanban** — board complet groupé par colonne rôle |
| `move_task` | **Kanban** — déplace une tâche vers une colonne (`kanban_column`) |
| `list_hermes_tasks` | **Cron Hermes** — toutes les tâches dev en attente, tous projets |
| `claim_hermes_task` | Réserve une tâche pour Hermes (anti-doublon cron) |
| `get_task` | Détail complet + outputs des étapes + bloc `hermes` + `kanban_column` |
| `create_task` | Crée une tâche dans le backlog |
| `update_task_status` | Change le statut d'une tâche (**legacy** — préférer `move_task` pour le Kanban) |
| `record_step_output` | Enregistre l'output d'une étape (ex. `dev` pour Hermes) |
| `request_gate` | Demande une validation humaine |
| `log_cost` | Enregistre un coût |
| `add_agent_output` | **Alias legacy** de `record_step_output` |

### Colonnes Kanban

Ordre d'affichage : `backlog` → `pm` → `test_lead` → `ux` → `dev` → `qa` → `qa_ux` → `security` → `done`

| Slug colonne | Label UI | Règle d'appartenance |
|--------------|----------|----------------------|
| `backlog` | Backlog | `status` = `backlog` ou `failed` |
| `pm` | Product Manager | `status` = `in_progress` + `current_role` = `pm` |
| `test_lead` | Test Lead | `in_progress` + `current_role` = `test_lead` |
| `ux` | UX Designer | `in_progress` + `current_role` = `ux` |
| `dev` | Dev | `waiting_hermes` **ou** `in_progress` + `current_role` in (`dev`, `hermes`) |
| `qa` | QA | `in_progress` + `current_role` = `qa` |
| `qa_ux` | QA UX | `in_progress` + `current_role` = `qa_ux` |
| `security` | Security | `in_progress` + `current_role` = `security` |
| `done` | Terminé | `status` = `done` |

Compatibilité données existantes :
- `in_review` → colonne `qa`
- `current_role` = `tech_lead` → colonne `test_lead`

### `list_kanban_board`

**Arguments :**

```json
{ "project_id": 1 }
```

**Réponse exemple :**

```json
{
  "columns": [
    { "slug": "backlog", "label": "Backlog", "tasks": [] },
    { "slug": "pm", "label": "Product Manager", "tasks": [{ "id": 1, "kanban_column": "pm", "status": "in_progress", "current_role": "pm" }] },
    { "slug": "dev", "label": "Dev", "tasks": [{ "id": 2, "kanban_column": "dev", "status": "waiting_hermes", "current_role": "hermes" }] }
  ],
  "column_order": ["backlog", "pm", "test_lead", "ux", "dev", "qa", "qa_ux", "security", "done"]
}
```

### `move_task`

**Arguments :**

```json
{
  "task_id": 42,
  "kanban_column": "qa"
}
```

**Effet :** met à jour `status` et `current_role` selon la colonne cible (ex. `dev` → `waiting_hermes` + `current_role: hermes`).

**Réponse :** `task` avec `status`, `current_role`, `kanban_column` résolu.

### `list_hermes_tasks`

**Arguments :**

```json
{ "limit": 10 }
```

**Réponse exemple (workflow hermes_only) :**

```json
{
  "workflow_mode": "hermes_only",
  "tasks": [
    {
      "task_id": 42,
      "uuid": "...",
      "title": "Ajouter auth OAuth",
      "type": "feature",
      "priority": "high",
      "module": "auth",
      "ready_since": "2026-06-27T10:00:00+00:00",
      "workflow_mode": "hermes_only",
      "hermes_action": "implement_dev",
      "project": {
        "id": 1,
        "name": "Maestro",
        "github_repo": "owner/repo",
        "github_branch": "main"
      },
      "instruction": "Implémenter selon le titre et la description..."
    }
  ],
  "count": 1,
  "polling_hint": "Traiter tasks[0] en priorité. Workflow : claim_hermes_task → implémenter selon titre/description → record_step_output(dev) → done."
}
```

Tri : priorité (`critical` → `high` → `medium` → `low`), puis ancienneté (`updated_at`).

### `claim_hermes_task`

**Arguments :**

```json
{ "task_id": 42 }
```

**Effet :** passe la tâche en `in_progress` + `current_role: hermes` (verrouillage atomique).

**Réponse :** contexte de travail + bloc `hermes` + `next_steps`.

### `get_task`

**Arguments :**

```json
{ "task_id": 42 }
```

**Bloc `hermes` dans la réponse (hermes_only) :**

```json
{
  "hermes": {
    "workflow_mode": "hermes_only",
    "should_process": true,
    "action": "implement_dev",
    "instruction": "Implémenter selon le titre et la description...",
    "ready_since": "2026-06-27T10:00:00+00:00",
    "github": {
      "repo": "owner/repo",
      "branch": "main"
    },
    "specs_preview": {
      "titre": "Ajouter auth OAuth",
      "description": "Implémenter le flux OAuth GitHub...",
      "module": "auth",
      "type": "feature",
      "priorité": "high"
    }
  }
}
```

### `create_task`

**Arguments requis :** `project_id`, `title`, `type`, `priority`

**Types :** `feature`, `bug`, `improvement`, `chore`

**Priorités :** `low`, `medium`, `high`, `critical`

### `record_step_output` (fin du travail dev Hermes)

> **Alias legacy :** `add_agent_output` reste accepté (paramètre `agent_type` mappé vers `role`).

**Arguments requis :** `task_id`, `role`, `output`, `model`

```json
{
  "task_id": 42,
  "role": "dev",
  "output": "Implémenté OAuth GitHub. Fichiers: AuthController.php, routes/web.php. Tests passent.",
  "model": "deepseek/deepseek-chat-v3-0324:free",
  "input_tokens": 0,
  "output_tokens": 0,
  "cost": 0
}
```

**Réponse :** inclut `workflow_mode` et le statut final de la tâche dans `task.status`.

En mode **hermes_only** (défaut) : `record_step_output(role=dev)` passe la tâche en **`done`**.

En mode **internal_pipeline** (`MAESTRO_INTERNAL_PIPELINE=true`) : Maestro relance QA → PR Expert → Doc.

### `update_task_status`

**Statuts valides :** `backlog`, `in_progress`, `waiting_hermes`, `in_review`, `done`, `failed`

> **Legacy :** pour déplacer une tâche sur le Kanban, préférer `move_task` qui met à jour `status` et `current_role` de façon cohérente.

---

## 8. Cycle de vie d'une tâche (hermes_only)

```
backlog
  → [Kanban : « Envoyer à Hermes » ou move_task(kanban_column=dev)]
  → colonne dev (waiting_hermes)     ← Hermes intervient ici
  → [Hermes : claim → dev via record_step_output]
  → done (colonne Terminé)
```

| Colonne Kanban | Statut / rôle | Qui agit |
|----------------|---------------|----------|
| `backlog` | `backlog` ou `failed` | Pas encore lancée |
| `pm` … `security` | `in_progress` + `current_role` | Pipeline interne (si activée) ou déplacement manuel |
| `dev` | `waiting_hermes` (+ `current_role: hermes`) | **Prête pour Hermes** |
| `dev` (en cours) | `in_progress` + `current_role: hermes` | Hermes en train d'implémenter |
| `done` | `done` | Terminée |

Hermes surveille la colonne **dev** (`waiting_hermes`) via **`list_hermes_tasks`**, pas via `list_tasks` projet par projet. Pour une vue board complète, utiliser **`list_kanban_board`**.

---

## 9. Pipeline interne (optionnel)

Par défaut, Maestro utilise le flux **hermes_only** : pas de PM/UX/QA automatique.

Pour réactiver la pipeline interne (PM → Test Lead → UX → Security → QA → QA UX → Doc) :

```env
MAESTRO_INTERNAL_PIPELINE=true
```

Dans ce mode :

- Le Kanban affiche les colonnes par rôle : Backlog → PM → Test Lead → UX → Dev → QA → QA UX → Security → Terminé
- Le Kanban affiche « Démarrer » au lieu de « Envoyer à Hermes »
- `hermes.specs_preview` contient les outputs PM/UX/Test Lead
- `planning_roles_completed` est présent dans les réponses Hermes
- `record_step_output(dev)` relance QA → PR Expert → Doc au lieu de marquer `done`

---

## 10. Dépannage

### HTTP 401 Unauthorized

| Cause | Solution |
|-------|----------|
| Token incorrect ou révoqué | Régénérer un token dans Paramètres MCP |
| Token avec espaces / retour ligne | Recopier proprement, sans espaces |
| `Bearer` collé deux fois | Hermes : token = valeur seule ; Claude Code : `Bearer TOKEN` dans le header |
| Mauvais compte utilisateur | Le token est lié au compte qui l'a généré |
| Token OAuth expiré (Cowork) | Reconnecter le connecteur OAuth |

### HTTP 404 sur `/api/mcp`

- Vérifier l'URL complète : `/api/mcp` (pas `/mcp` seul)
- Vérifier que Maestro tourne sur le bon port

### Token « Jamais utilisé » dans l'UI

- Hermes / Claude n'a pas encore appelé l'endpoint
- Tester avec le `curl initialize` (section 2)
- Vérifier les logs Hermes et la connectivité réseau

### Mauvais port ou URL

- L'URL affichée dans **Paramètres → Intégrations MCP** est celle à utiliser
- En production, aligner `APP_URL` dans `.env` sur l'URL HTTPS réelle (requis pour OAuth Cowork)

### `list_hermes_tasks` retourne `count: 0`

- Aucune tâche en statut `waiting_hermes`
- Depuis le Kanban, cliquer **« Envoyer à Hermes »** sur une tâche en backlog
- Vérifier que le projet est **actif** et lié au compte du token

### Connexion OK mais projets vides

- Le token est lié à l'utilisateur qui l'a créé
- Vérifier que des projets **actifs** existent pour ce compte

---

## 11. Sécurité

- Un token = accès MCP complet au compte Maestro associé
- Révoquer immédiatement un token compromis : Paramètres MCP → Révoquer
- Un token par environnement (local / prod) et par client (Hermes / Claude Code)
- Ne jamais committer un token dans git

---

## 12. Références

- Déploiement prod : [DEPLOYMENT.md](./DEPLOYMENT.md)
- UI tokens : Maestro → Paramètres → Intégrations MCP
- Documentation API dans l'app : Paramètres → Intégrations MCP → Documentation API
