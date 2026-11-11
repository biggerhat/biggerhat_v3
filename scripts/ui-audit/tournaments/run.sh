#!/usr/bin/env bash
# Tournament browser pass: fresh SQLite DB + seed (re-done before each file), local PHP server, then every flows*.cjs in order.
# Local use only. Needs a built frontend (npm run build) and a Chrome:
#   CHROME=/path/to/chrome-headless-shell [LD_LIBRARY_PATH=...] scripts/ui-audit/tournaments/run.sh
# Env: WORKDIR (default /tmp/ui-audit-tournaments), PORT (default 8765), SHOTS (screenshots dir).
set -euo pipefail
cd "$(dirname "$0")/../../.."
ROOT=$(pwd)
: "${CHROME:?set CHROME to a Chromium / chrome-headless-shell binary}"
WORKDIR=${WORKDIR:-/tmp/ui-audit-tournaments}
PORT=${PORT:-8765}
mkdir -p "$WORKDIR"

export DB_CONNECTION=sqlite DB_DATABASE="$WORKDIR/browser.sqlite" SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync BROADCAST_CONNECTION=log MAIL_MAILER=log
export APP_URL="http://127.0.0.1:$PORT" BASE="http://127.0.0.1:$PORT" IDS="$WORKDIR/ids.json" SHOTS=${SHOTS:-$WORKDIR/shots} CHROME

# Each flows file mutates the data it works on, so every file starts from a freshly migrated + seeded database.
reset_db() {
    rm -f "$DB_DATABASE"; touch "$DB_DATABASE"
    APP_ENV=testing php artisan migrate --force > /dev/null
    SEEDOUT="$IDS" APP_ENV=local php artisan tinker --execute "$(cat scripts/ui-audit/tournaments/seed.php)" | tail -1
}
reset_db

# public/hot points assets at a dead Vite server; a plain `php -S` from public/ keeps the DB_* env (artisan serve drops it)
[ -e public/hot ] && { echo "public/hot exists - move it aside and run npm run build first" >&2; exit 1; }
(cd public && APP_ENV=local php -S "127.0.0.1:$PORT" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php > "$WORKDIR/serve.log" 2>&1) &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 2

status=0
for f in scripts/ui-audit/tournaments/flows*.cjs; do
    echo "== $(basename "$f")"
    [ "$f" = "$(ls scripts/ui-audit/tournaments/flows*.cjs | head -1)" ] || reset_db > /dev/null
    node "$f" || status=1
done
echo "screenshots: $SHOTS"
exit $status
