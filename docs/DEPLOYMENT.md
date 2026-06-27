# Déploiement Maestro + Hermes — Hetzner CX21

## Prérequis serveur

- Ubuntu 22.04
- PHP 8.3 + FPM + extensions (pgsql, redis, mbstring, xml, curl)
- Nginx
- PostgreSQL 15
- Redis 7
- Node.js 20
- Supervisor

## Installation Maestro

```bash
git clone <repo> /var/www/maestro
cd /var/www/maestro
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

## Variables `.env` production

```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
CACHE_STORE=redis
BROADCAST_CONNECTION=pusher
```

## Supervisor — Horizon

```ini
[program:maestro-horizon]
process_name=%(program_name)s
command=php /var/www/maestro/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/maestro/storage/logs/horizon.log
stopwaitsecs=3600
```

## Scheduler

Crontab `www-data` :

```
* * * * * cd /var/www/maestro && php artisan schedule:run >> /dev/null 2>&1
```

## GitHub

1. Créer OAuth App (callback : `https://votre-domaine/auth/github/callback`)
2. Configurer webhook PR : `https://votre-domaine/webhooks/github`
3. Secret webhook dans `GITHUB_WEBHOOK_SECRET`

## SSL

```bash
sudo certbot --nginx -d maestro.votre-domaine.com
```

## Hermes (même serveur)

L'exécution du code est déléguée à [Hermes](https://hermes-agent.nousresearch.com/) sur le même serveur Hetzner.

### Installation Hermes

```bash
curl -fsSL https://hermes-agent.nousresearch.com/install.sh | bash
hermes setup --portal
```

### Accès MCP Maestro

Endpoint : `https://maestro.votre-domaine.com/api/mcp`

#### Hermes (Bearer token)

1. Maestro → **Paramètres** → **Accès MCP** → générer un token (affiché une seule fois)
2. Configurer Hermes :

```yaml
mcp_servers:
  maestro:
    url: https://maestro.votre-domaine.com/api/mcp
    auth:
      type: bearer
      token: "<token_généré_dans_maestro>"
```

#### Claude Code (Bearer token)

```bash
claude mcp add --transport http maestro https://maestro.votre-domaine.com/api/mcp \
  --header "Authorization: Bearer <token_généré_dans_maestro>"
```

#### Claude Cowork (OAuth 2.1)

1. Cowork → **Paramètres** → **Connecteurs** → **Ajouter un connecteur custom**
2. URL : `https://maestro.votre-domaine.com/api/mcp` (pas de token à coller)
3. Se connecter à Maestro dans le navigateur et approuver l’accès
4. `APP_URL` doit être l’URL HTTPS exacte (requis pour OAuth)

#### Validation connecteur Cowork

```bash
# Métadonnées OAuth
curl -s https://maestro.votre-domaine.com/.well-known/oauth-protected-resource

# 401 avec WWW-Authenticate (déclenche le flux OAuth dans Cowork)
curl -s -o /dev/null -w "%{http_code}\n" -X POST https://maestro.votre-domaine.com/api/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

Puis dans Cowork : connecteur custom → OAuth → login Maestro → « Liste mes projets Maestro ».

### Modèles OpenRouter recommandés (dev)

- `deepseek/deepseek-chat-v3-0324:free` — code, gratuit
- `meta-llama/llama-3.1-70b-instruct:free` — tool use, gratuit

### Telegram (optionnel)

```bash
hermes gateway telegram
```

### Test bout en bout

1. Hermes appelle `create_task` via MCP
2. La tâche apparaît dans le kanban Maestro
3. Après les agents de planning + gate tech, la tâche passe en statut **Hermes** (`waiting_hermes`)
4. Hermes implémente le code et appelle `add_agent_output` avec `agent_type: dev`
5. Le pipeline Maestro reprend sur QA → PR Expert → Doc

## Checklist post-déploiement

- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] Horizon actif
- [ ] Soketi/Pusher accessible
- [ ] Permissions `storage/` et `bootstrap/cache/`
- [ ] Token MCP généré (Hermes / Claude Code) ou connecteur Cowork OAuth validé
- [ ] Test E2E Hermes → Maestro validé
- [ ] Test connecteur Cowork → `list_projects` via MCP
