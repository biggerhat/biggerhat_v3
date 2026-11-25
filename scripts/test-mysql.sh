#!/usr/bin/env bash
# Runs the PHP test suite against a real MySQL 8 (production's database; the normal suite runs on in-memory SQLite).
#
#   scripts/test-mysql.sh                       # the whole suite, in parallel
#   scripts/test-mysql.sh --filter=BattleReport # anything after the script name goes to `php artisan test`
#
# The database is started in a throwaway Docker container with its data in memory and with fsync turned off. That is safe because nothing in it
# lasts, and it is what makes this fast: migrating one database takes about 13 seconds instead of several minutes on a disk-backed container
# (every parallel worker migrates its own database). CI runs this same script (.github/workflows/ci.yml, job "mysql").
set -euo pipefail

cd "$(dirname "$0")/.."

NAME="${MYSQL_TEST_CONTAINER:-biggerhat-mysql-test}"
PORT="${MYSQL_TEST_PORT:-33306}"
PASSWORD=secret

docker rm -f "$NAME" >/dev/null 2>&1 || true
docker run -d --name "$NAME" \
    --tmpfs /var/lib/mysql:rw,size=2g \
    -e MYSQL_ROOT_PASSWORD="$PASSWORD" -e MYSQL_DATABASE=biggerhat_test \
    -p "127.0.0.1:$PORT:3306" \
    mysql:8.0 \
    --innodb-flush-log-at-trx-commit=0 --sync-binlog=0 --innodb-doublewrite=0 --innodb-flush-method=nosync --skip-log-bin \
    --max-connections=300 >/dev/null
trap 'docker rm -f "$NAME" >/dev/null 2>&1 || true' EXIT

echo "Waiting for MySQL..."
for _ in $(seq 1 60); do
    if docker exec "$NAME" mysqladmin ping -h 127.0.0.1 -p"$PASSWORD" --silent >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 2
done
if [ -z "${ready:-}" ]; then
    echo "MySQL did not start." >&2
    docker logs "$NAME" | tail -20 >&2
    exit 1
fi

export DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT="$PORT" DB_DATABASE=biggerhat_test DB_USERNAME=root DB_PASSWORD="$PASSWORD"

echo "Migrating a fresh database..."
php artisan migrate:fresh --force --no-interaction

echo "Running the tests..."
php artisan test -p "$@"
