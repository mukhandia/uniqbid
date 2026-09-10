#!/usr/bin/env bash
set -euo pipefail

# Runs on the CloudPanel server after rsync.
# Keeps downtime short: maintenance mode only around migrate/optimize.

APP_PATH="${1:?App path required}"
PHP_BIN="${PHP_BIN:-php}"
SCHEDULER_MARKER="uniqbid-scheduler"
MAINTENANCE_ON=0

leave_maintenance() {
  if [[ "$MAINTENANCE_ON" -eq 1 ]]; then
    echo "==> Leaving maintenance mode"
    $PHP_BIN artisan up || true
    MAINTENANCE_ON=0
  fi
}

trap leave_maintenance EXIT

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
MAINTENANCE_ON=1

echo "==> Running migrations"
$PHP_BIN artisan migrate --force --no-interaction

echo "==> Seeding CMS content (skipped if content already exists)"
$PHP_BIN artisan db:seed --class=ContentSeeder --force --no-interaction

echo "==> Seeding roles and permissions (idempotent)"
$PHP_BIN artisan db:seed --class=RolePermissionSeeder --force --no-interaction

echo "==> Seeding launch auction catalogue (idempotent)"
$PHP_BIN artisan db:seed --class=LaunchAuctionCatalogueSeeder --force --no-interaction

echo "==> Syncing catalogue product images (idempotent)"
$PHP_BIN artisan db:seed --class=SyncCatalogueProductImagesSeeder --force --no-interaction

echo "==> Ensuring admin user exists"
$PHP_BIN artisan uniqbid:ensure-admin --no-interaction

echo "==> Syncing live auction catalogue (orphan / non-auction-channel sales)"
$PHP_BIN artisan uniqbid:sync-live-catalogue --no-interaction

echo "==> Ensuring storage symlink"
$PHP_BIN artisan storage:link --force || true

echo "==> Rebuilding caches"
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan optimize
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache || true

echo "==> Restarting queues (if workers are running)"
$PHP_BIN artisan queue:restart || true

echo "==> Publishing Livewire and Flux assets"
bash scripts/publish-frontend-assets.sh

leave_maintenance
trap - EXIT

echo "==> Ensuring Laravel scheduler cron entry"
CRON_ENTRY="* * * * * cd \"$APP_PATH\" && $PHP_BIN artisan schedule:run >> /dev/null 2>&1 # $SCHEDULER_MARKER"
if ! command -v crontab >/dev/null 2>&1; then
  echo "Warning: crontab is unavailable. Add this cron job in CloudPanel:"
  echo "  $CRON_ENTRY"
elif (crontab -l 2>/dev/null | awk -v marker="$SCHEDULER_MARKER" 'index($0, marker) == 0'; echo "$CRON_ENTRY") | crontab -; then
  echo "Scheduler cron entry installed."
else
  echo "Warning: could not write crontab (common on CloudPanel). Add this cron job manually:"
  echo "  $CRON_ENTRY"
fi

echo "==> Deploy finished successfully"
