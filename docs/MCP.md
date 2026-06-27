# Maestro — Serveur MCP

Guide d'intégration pour **Hermes** et **Claude** (Code / Cowork).

---

## 1. Connexion

| Paramètre | Valeur |
|-----------|--------|
| **URL** | `{URL_MAESTRO}/api/mcp` |
| **Protocole** | JSON-RPC 2.0, POST HTTP |
| **Headers** | `Content-Type: application/json` |
| **Auth** | `Authorization: Bearer <TOKEN>` |

**Où trouver l'URL exacte :** Maestro → **Paramètres → Intégrations MCP**

Exemples :

- Local : `http://127.0.0.1:8001/api/mcp`
- Prod : `https://maestro.votre-domaine.com/api/mcp`

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
curl -s -X POST http://127.0.0.1:8001/api/mcp \
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
curl -s -X POST http://127.0.0.1:8001/api/mcp \
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
    url: http://127.0.0.1:8001/api/mcp
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
Tu es connecté au serveur MCP Maestro. Workflow dev :

1. Appelle list_hermes_tasks() pour voir les tâches prêtes.
2. Si count = 0, arrête-toi.
3. Prends tasks[0] (priorité la plus haute).
4. Appelle claim_hermes_task(task_id) pour réserver la tâche.
5. Appelle get_task(task_id) et lis hermes.specs_preview (PM, UX, Tech Lead).
6. Clone le dépôt GitHub indiqué (hermes.github.repo, branche hermes.github.branch).
7. Implémente le code selon les specs.
8. Appelle add_agent_output avec :
   - task_id
   - agent_type: "dev"
   - output: résumé de ce qui a été fait (fichiers, décisions, commandes)
   - model: le modèle utilisé
   - cost: 0 si gratuit
9. Maestro reprendra automatiquement QA → PR Expert → Doc.

En cas d'échec avant l'étape 8 :
  update_task_status(task_id, "waiting_hermes")
```

Fréquence suggérée : `*/5 * * * *`

---

## 4. Configuration Claude Code

```bash
claude mcp add --transport http maestro http://127.0.0.1:8001/api/mcp \
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
2. URL : `https://maestro.votre-domaine.com/api/mcp`
3. Se connecter à Maestro dans le navigateur et approuver l'accès

Prérequis côté Maestro :

- `APP_URL` doit être l'URL HTTPS exacte (ex. `https://maestro.votre-domaine.com`)
- Routes OAuth accessibles :
  - `/.well-known/oauth-protected-resource`
  - `/.well-known/oauth-authorization-server`

Test :

```bash
curl -s https://maestro.votre-domaine.com/.well-known/oauth-protected-resource
```

---

## 6. Protocole MCP

### Méthodes supportées

| Méthode | Description |
|---------|-------------|
| `initialize` | Handshake, retourne les capacités du serveur |
| `tools/list` | Liste les 10 tools disponibles |
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

---

## 7. Tools disponibles

| Tool | Description |
|------|-------------|
| `list_projects` | Projets actifs de l'utilisateur |
| `list_tasks` | Tâches d'un projet (`project_id`, filtre `status` optionnel) |
| `list_hermes_tasks` | **Cron Hermes** — toutes les tâches dev en attente, tous projets |
| `claim_hermes_task` | Réserve une tâche pour Hermes (anti-doublon cron) |
| `get_task` | Détail complet + outputs agents + bloc `hermes` |
| `create_task` | Crée une tâche dans le backlog |
| `update_task_status` | Change le statut d'une tâche |
| `add_agent_output` | Enregistre l'output d'un agent (ex. `dev` pour Hermes) |
| `request_gate` | Demande une validation humaine |
| `log_cost` | Enregistre un coût |

### `list_hermes_tasks`

**Arguments :**

```json
{ "limit": 10 }
```

**Réponse exemple :**

```json
{
  "tasks": [
    {
      "task_id": 42,
      "uuid": "...",
      "title": "Ajouter auth OAuth",
      "type": "feature",
      "priority": "high",
      "module": "auth",
      "ready_since": "2026-06-27T10:00:00+00:00",
      "hermes_action": "implement_dev",
      "project": {
        "id": 1,
        "name": "Maestro",
        "github_repo": "owner/repo",
        "github_branch": "main"
      },
      "planning_agents_completed": ["pm", "ux", "tech_lead", "security"],
      "instruction": "Implémenter le code selon les specs..."
    }
  ],
  "count": 1,
  "polling_hint": "Traiter tasks[0] en priorité. Workflow : claim_hermes_task → implémenter → add_agent_output(dev)."
}
```

Tri : priorité (`critical` → `high` → `medium` → `low`), puis ancienneté (`updated_at`).

### `claim_hermes_task`

**Arguments :**

```json
{ "task_id": 42 }
```

**Effet :** passe la tâche en `in_progress` + `current_agent: hermes` (verrouillage atomique).

**Réponse :** contexte de travail + bloc `hermes` + `next_steps`.

### `get_task`

**Arguments :**

```json
{ "task_id": 42 }
```

**Bloc `hermes` dans la réponse :**

```json
{
  "hermes": {
    "should_process": true,
    "action": "implement_dev",
    "instruction": "...",
    "ready_since": "2026-06-27T10:00:00+00:00",
    "github": {
      "repo": "owner/repo",
      "branch": "main"
    },
    "planning_agents_completed": ["pm", "ux", "tech_lead", "security"],
    "specs_preview": {
      "tech_lead": "Architecture et plan d'implémentation...",
      "ux": "Wireframes et parcours utilisateur...",
      "pm": "Specs produit..."
    }
  }
}
```

### `create_task`

**Arguments requis :** `project_id`, `title`, `type`, `priority`

**Types :** `feature`, `bug`, `improvement`, `chore`

**Priorités :** `low`, `medium`, `high`, `critical`

### `add_agent_output` (fin du travail dev Hermes)

**Arguments requis :** `task_id`, `agent_type`, `output`, `model`

```json
{
  "task_id": 42,
  "agent_type": "dev",
  "output": "Implémenté OAuth GitHub. Fichiers: AuthController.php, routes/web.php. Tests passent.",
  "model": "deepseek/deepseek-chat-v3-0324:free",
  "input_tokens": 0,
  "output_tokens": 0,
  "cost": 0
}
```

Après `add_agent_output` avec `agent_type: dev`, Maestro relance automatiquement la chaîne d'agents : **QA → PR Expert → Doc**.

### `update_task_status`

**Statuts valides :** `backlog`, `in_progress`, `waiting_hermes`, `in_review`, `done`, `failed`

---

## 8. Cycle de vie d'une tâche

```
backlog
  → [Maestro : PM, UX, Tech Lead, Security]
  → waiting_hermes          ← Hermes intervient ici
  → [Hermes : dev via add_agent_output]
  → in_progress             ← Maestro : QA, PR Expert, Doc
  → done
```

| Statut | Qui agit |
|--------|----------|
| `backlog` | Pas encore démarrée |
| `in_progress` | Agents Maestro (PM, UX…) ou Hermes en cours de dev |
| `waiting_hermes` | **Prête pour Hermes** — colonne Hermes du Kanban |
| `in_review` | En revue |
| `done` | Terminée |
| `failed` | Échec |

Hermes surveille `waiting_hermes` via **`list_hermes_tasks`**, pas via `list_tasks` projet par projet.

---

## 9. Dépannage

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
- Si Maestro tourne sur le port **8001**, ne pas configurer le port **8000**
- En production, aligner `APP_URL` dans `.env` sur l'URL HTTPS réelle (requis pour OAuth Cowork)

### `list_hermes_tasks` retourne `count: 0`

- Aucune tâche en statut `waiting_hermes`
- Lancer d'abord les agents Maestro depuis le Kanban (« Démarrer les agents »)
- La tâche doit passer par PM / UX / Tech Lead / Security avant d'être prête pour Hermes

### Connexion OK mais projets vides

- Le token est lié à l'utilisateur qui l'a créé
- Vérifier que des projets **actifs** existent pour ce compte

---

## 10. Sécurité

- Un token = accès MCP complet au compte Maestro associé
- Révoquer immédiatement un token compromis : Paramètres MCP → Révoquer
- Un token par environnement (local / prod) et par client (Hermes / Claude Code)
- Ne jamais committer un token dans git

---

## 11. Références

- Déploiement prod : [DEPLOYMENT.md](./DEPLOYMENT.md)
- UI tokens : Maestro → Paramètres → Intégrations MCP
