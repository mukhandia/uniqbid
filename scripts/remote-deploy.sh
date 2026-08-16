#!/usr/bin/env bash
set -euo pipefail

# Runs on the CloudPanel server after rsync.
# Keeps downtime short: maintenance mode only around migrate/optimize.

APP_PATH="${1:?App path required}"
PHP_BIN="${PHP_BIN:-php}"
SCHEDULER_MARKER="uniqbid-scheduler"

cd "$APP_PATH"

echo "==> Ensuring writable storage/cache directories exist"
mkdir -p \
  storage/framework/{cache,sessions,views} \
  storage/logs \
  storage/app/public \
  bootstrap/cache

echo "==> Installing PHP dependencies (production)"
composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --prefer-dist \
  --no-progress

echo "==> Entering maintenance mode"
$PHP_BIN artisan down --retry=60 --refresh=15 || true

echo "==> Running migrations"
$PHP_BIN artisan migrate --force --no-interaction

echo "==> Ensuring storage symlink"
$PHP_BIN artisan storage:link --force || true

echo "==> Rebuilding caches"
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan optimize
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache || true

echo "==> Restarting queues (if workers are running)"
$PHP_BIN artisan queue:restart || true

echo "==> Ensuring Laravel scheduler cron entry"
if ! command -v crontab >/dev/null 2>&1; then
  echo "crontab is required to run the Laravel scheduler." >&2
  exit 1
fi

CRON_ENTRY="* * * * * cd \"$APP_PATH\" && $PHP_BIN artisan schedule:run >> /dev/null 2>&1 # $SCHEDULER_MARKER"
(crontab -l 2>/dev/null | awk -v marker="$SCHEDULER_MARKER" 'index($0, marker) == 0'; echo "$CRON_ENTRY") | crontab -

echo "==> Leaving maintenance mode"
$PHP_BIN artisan up

echo "==> Deploy finished successfully"
