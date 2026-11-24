#!/usr/bin/env bash
set -euo pipefail

# Publish Livewire/Flux JS into public/ so nginx can serve them as real files.
# CloudPanel/nginx often uses `location ~* \.(js|css)$ { try_files $uri =404; }`,
# which blocks Laravel-routed asset URLs like /livewire-*/livewire.js and /flux/flux.js.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
cd "$ROOT"

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

if ! grep -qE '^APP_KEY=.+' .env; then
  $PHP_BIN artisan key:generate --force --no-interaction
fi

echo "==> Publishing Livewire assets to public/vendor/livewire"
$PHP_BIN artisan livewire:publish --assets --no-interaction

echo "==> Publishing Flux assets to public/flux"
mkdir -p public/flux
FLUX_SRC="vendor/livewire/flux/dist/flux-lite.min.js"
if [[ ! -f "$FLUX_SRC" ]]; then
  echo "Missing Flux asset: $FLUX_SRC" >&2
  exit 1
fi

cp "$FLUX_SRC" public/flux/flux.js
cp "$FLUX_SRC" public/flux/flux.min.js

for required in public/flux/flux.js public/vendor/livewire/manifest.json; do
  if [[ ! -s "$required" ]]; then
    echo "Missing required frontend asset after publish: $required" >&2
    exit 1
  fi
done

echo "==> Frontend framework assets ready"
