# Déploiement Maestro — Hetzner CX21

## Prérequis serveur

- Ubuntu 22.04
- PHP 8.3 + FPM + extensions (pgsql, redis, mbstring, xml, curl)
- Nginx
- PostgreSQL 15
- Redis 7
- Node.js 20
- Supervisor
- Claude Code CLI : `npm install -g @anthropic-ai/claude-code`

## Installation

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
MAESTRO_REPOS_PATH=/srv/maestro-repos
```

## Repos GitHub

```bash
sudo mkdir -p /srv/maestro-repos
sudo chown www-data:www-data /srv/maestro-repos
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

## Checklist post-déploiement

- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] Horizon actif
- [ ] Soketi/Pusher accessible
- [ ] Permissions `storage/` et `bootstrap/cache/`
