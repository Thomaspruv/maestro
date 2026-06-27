# Maestro MCP — Guide d'intégration (Hermes & Claude)

Ce document décrit comment connecter **Hermes**, **Claude Code** ou **Claude Cowork** au serveur MCP Maestro.

---

## 1. Vue d'ensemble

| Élément | Valeur |
|---------|--------|
| **Endpoint MCP** | `{URL_MAESTRO}/api/mcp` — voir Paramètres → Intégrations MCP (URL exacte affichée) |
| **Protocole** | JSON-RPC 2.0 over HTTP POST |
| **Authentification** | Header `Authorization: Bearer <token>` |
| **Format requête** | `Content-Type: application/json` |

Exemple d'URL locale : `http://localhost:8001/api/mcp`  
Exemple production : `https://maestro.votre-domaine.com/api/mcp`

> L'URL affichée dans Maestro → **Paramètres → Intégrations MCP** est toujours la bonne à utiliser.

---

## 2. Générer un token (Hermes & Claude Code)

1. Connectez-vous à Maestro avec votre compte (`thomas@mail.com` ou autre)
2. Allez dans **Paramètres → Intégrations MCP**
3. Donnez un nom au token (ex. `Hermes prod`)
4. Cliquez **Générer un token**
5. **Copiez le token immédiatement** — il ne sera plus affiché ensuite

Le token est une chaîne aléatoire de **40 caractères**. Il est stocké hashé (SHA-256) en base ; Maestro ne peut pas le retrouver après génération.

### Vérifier que le token fonctionne

Remplacez `VOTRE_TOKEN` et l'URL :

```bash
curl -s -X POST http://localhost:8001/api/mcp \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

Réponse attendue (HTTP 200) :

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

Lister les tools disponibles :

```bash
curl -s -X POST http://localhost:8001/api/mcp \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
```

Dans Maestro → Paramètres MCP, la colonne **Dernière utilisation** du token doit se mettre à jour après un appel réussi.

---

## 3. Configuration Hermes

### Prérequis

- Hermes installé sur le serveur ([hermes-agent.nousresearch.com](https://hermes-agent.nousresearch.com/))
- Token MCP généré dans Maestro (section 2)
- Maestro accessible depuis le serveur Hermes (même machine ou réseau)

### Fichier de config Hermes

```yaml
mcp_servers:
  maestro:
    url: https://maestro.votre-domaine.com/api/mcp
    auth:
      type: bearer
      token: "collez_ici_le_token_40_caracteres"
```

**Important :**
- Collez **uniquement** la valeur du token, sans le préfixe `Bearer `
- Hermes ajoute automatiquement `Authorization: Bearer …` dans les requêtes
- Ne mettez pas de guillemets ou d'espaces en trop dans le token

### Routine cron Hermes (traitement des tâches dev)

Workflow recommandé toutes les 5 minutes :

```
1. list_hermes_tasks()           → tâches prêtes pour Hermes
2. claim_hermes_task(task_id)    → réserve la tâche (anti-doublon)
3. get_task(task_id)             → specs PM / UX / Tech Lead
4. [implémenter le code]
5. add_agent_output(task_id, agent_type=dev, output=..., model=...)
```

En cas d'échec avant l'étape 5 : `update_task_status(task_id, waiting_hermes)` pour remettre la tâche en file.

---

## 4. Configuration Claude Code

```bash
claude mcp add --transport http maestro https://maestro.votre-domaine.com/api/mcp \
  --header "Authorization: Bearer VOTRE_TOKEN"
```

Vérifier :

```bash
claude mcp list
```

Puis dans Claude Code : « Liste mes projets Maestro » → doit appeler `list_projects`.

---

## 5. Configuration Claude Cowork (OAuth)

Cowork n'utilise **pas** de token statique. Flux OAuth 2.1 :

1. Cowork → **Paramètres → Connecteurs → Ajouter un connecteur custom**
2. URL : `https://maestro.votre-domaine.com/api/mcp`
3. Se connecter à Maestro dans le navigateur et approuver l'accès

Prérequis côté Maestro :
- `APP_URL` doit être l'URL HTTPS exacte (ex. `https://maestro.votre-domaine.com`)
- Les routes OAuth doivent être accessibles :
  - `/.well-known/oauth-protected-resource`
  - `/.well-known/oauth-authorization-server`

Test sans Cowork :

```bash
curl -s https://maestro.votre-domaine.com/.well-known/oauth-protected-resource
```

---

## 6. Tools MCP disponibles

| Tool | Description |
|------|-------------|
| `list_projects` | Projets actifs de l'utilisateur |
| `list_tasks` | Tâches d'un projet (filtre `status` optionnel) |
| `list_hermes_tasks` | **Toutes** les tâches prêtes pour Hermes (cron) |
| `get_task` | Détail + outputs agents + bloc `hermes` |
| `claim_hermes_task` | Réserve une tâche pour Hermes (anti-doublon) |
| `create_task` | Crée une tâche dans le backlog |
| `update_task_status` | Change le statut d'une tâche |
| `add_agent_output` | Enregistre l'output d'un agent (ex. `dev` pour Hermes) |
| `request_gate` | Demande une validation humaine |
| `log_cost` | Enregistre un coût |

### Exemple d'appel tool

```bash
curl -s -X POST http://localhost:8001/api/mcp \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 3,
    "method": "tools/call",
    "params": {
      "name": "list_hermes_tasks",
      "arguments": {}
    }
  }'
```

La réponse contient le JSON dans `result.content[0].text` (string JSON à parser).

---

## 7. Statuts de tâche

| Statut | Signification |
|--------|---------------|
| `backlog` | Pas encore démarrée |
| `in_progress` | Agents Maestro en cours (PM, UX, Tech Lead…) |
| `waiting_hermes` | **Prête pour Hermes** — implémentation dev |
| `in_review` | En revue |
| `done` | Terminée |
| `failed` | Échec |

Hermes doit surveiller `waiting_hermes` via `list_hermes_tasks`.

---

## 8. Dépannage

### HTTP 401 Unauthorized

| Cause | Solution |
|-------|----------|
| Token incorrect ou révoqué | Régénérer un token dans Paramètres MCP |
| Token avec espaces / retour ligne | Recopier proprement, sans espaces |
| `Bearer` collé deux fois | Config Hermes : token = valeur seule ; Claude Code : `Bearer TOKEN` dans le header |
| Mauvais compte utilisateur | Le token est lié au compte qui l'a généré — ses projets uniquement |
| Token OAuth expiré (Cowork) | Reconnecter le connecteur OAuth |

### HTTP 404 sur `/api/mcp`

- Vérifier l'URL complète : `/api/mcp` (pas `/mcp` seul)
- Vérifier que Maestro tourne et que `APP_URL` est correct

### Token « Jamais utilisé » dans l'UI

- Hermes / Claude n'a pas encore appelé l'endpoint
- Vérifier avec le `curl initialize` ci-dessus
- Vérifier les logs Hermes et la connectivité réseau vers Maestro

### `APP_URL` incorrect ou port différent

- L'URL MCP affichée dans **Paramètres → Intégrations MCP** est celle à utiliser (basée sur l'URL de navigation)
- Si Maestro tourne sur le port **8001** (`php artisan serve --port=8001`), ne configurez pas Hermes avec le port **8000**
- Alignez `APP_URL` dans `.env` sur l'URL réelle en production (OAuth Cowork)
- Ne pas mélanger `localhost` et `127.0.0.1` si cookies OAuth

### Connexion OK mais projets vides

- Le token est lié à l'utilisateur qui l'a créé
- Vérifier que des projets **actifs** existent pour ce compte

---

## 9. Sécurité

- Un token = accès complet MCP au compte Maestro associé
- Révoquer immédiatement un token compromis (Paramètres MCP → Révoquer)
- Un token par environnement (local / prod) et par client (Hermes / Claude Code)
- Ne jamais committer un token dans git

---

## 10. Références

- Déploiement prod + cron Hermes : [DEPLOYMENT.md](./DEPLOYMENT.md)
- UI tokens : Maestro → Paramètres → Intégrations MCP
