#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

cleanup() {
    echo ""
    echo "Arrêt des services Maestro..."
    kill "$(jobs -p)" 2>/dev/null || true
    exit
}

trap cleanup SIGINT SIGTERM

export NODE_ENV=development

QUEUE_CONNECTION="$(grep -E '^QUEUE_CONNECTION=' .env 2>/dev/null | cut -d= -f2- | sed -e 's/^[" ]*//' -e 's/[" ]*$//' || true)"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"

echo "▶ Maestro — démarrage dev (QUEUE_CONNECTION=${QUEUE_CONNECTION})"
echo ""

chmod +x scripts/queue-worker.sh 2>/dev/null || true

# Redémarre les workers queue:work déjà en cours pour qu'ils rechargent le code
php artisan queue:restart >/dev/null 2>&1 || true

php artisan serve &
npm run dev &

# Horizon si redis, sinon queue:work database (agents, dev-agent, default)
bash scripts/queue-worker.sh &

php artisan schedule:work &

echo "Services lancés :"
echo "  • App      → http://127.0.0.1:8000"
echo "  • Vite     → npm run dev"
if [ "$QUEUE_CONNECTION" = "database" ]; then
    echo "  • Queue    → php artisan queue:work database --queue=agents,dev-agent,default"
    echo "             (Horizon ne consomme pas cette queue — ne pas compter sur un Horizon d'un autre projet)"
elif [ "$QUEUE_CONNECTION" = "redis" ]; then
    echo "  • Queue    → php artisan horizon"
else
    echo "  • Queue    → queue:work ${QUEUE_CONNECTION}"
fi
echo "  • Scheduler → php artisan schedule:work"
echo ""
echo "Ctrl+C pour tout arrêter."
echo ""

wait
