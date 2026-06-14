#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

QUEUE_CONNECTION="$(grep -E '^QUEUE_CONNECTION=' .env 2>/dev/null | cut -d= -f2- | tr -d '"'"' | tr -d ' ' || true)"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"

QUEUES="agents,dev-agent,default"

if [ "$QUEUE_CONNECTION" = "redis" ] && php artisan list --raw 2>/dev/null | grep -qx 'horizon'; then
    echo "▶ Queue driver: redis — Horizon"
    exec php artisan horizon
fi

if [ "$QUEUE_CONNECTION" = "database" ]; then
    echo "▶ Queue driver: database — queue:work (Horizon n'écoute pas ce driver)"
    exec php artisan queue:work database --queue="$QUEUES" --tries=1 --timeout=960
fi

echo "▶ Queue driver: ${QUEUE_CONNECTION} — queue:work"
exec php artisan queue:work "$QUEUE_CONNECTION" --queue="$QUEUES" --tries=1 --timeout=960
