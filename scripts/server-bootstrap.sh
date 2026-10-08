#!/usr/bin/env bash
set -euo pipefail

# One-time setup for a NEW server/database, run manually over SSH after the
# first deploy (scripts/remote-deploy.sh) has run migrations.
# Do not add this to CI/CD: several of these seeders overwrite live data
# (catalogue images, category names, role permissions) every time they run.
#
# Usage: bash scripts/server-bootstrap.sh /path/to/app

APP_PATH="${1:?App path required}"
PHP_BIN="${PHP_BIN:-php}"

cd "$APP_PATH"

echo "This seeds a fresh database (settings, roles, content, catalogue, launch auctions, admin)."
read -r -p "Continue on $(hostname) in $APP_PATH? [y/N] " answer
if [[ "${answer,,}" != "y" ]]; then
  echo "Aborted."
  exit 1
fi

seed() {
  echo "==> db:seed --class=$1"
  $PHP_BIN artisan db:seed --class="$1" --force --no-interaction
}

seed SiteSettingSeeder
seed RolePermissionSeeder
$PHP_BIN artisan permission:cache-reset || true
seed ContentSeeder
seed SyncCatalogueTaxonomySeeder
seed LaunchAuctionCatalogueSeeder
seed SyncCatalogueProductImagesSeeder

echo "==> Ensuring admin user exists"
$PHP_BIN artisan uniqbid:ensure-admin --no-interaction

$PHP_BIN artisan optimize:clear
$PHP_BIN artisan optimize

echo "==> Bootstrap finished"
