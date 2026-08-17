#!/usr/bin/env bash
set -euo pipefail

# Publish Livewire/Flux JS into public/ so nginx can serve them as real files.
# CloudPanel/nginx often uses `location ~* \.(js|css)$ { try_files $uri =404; }`,
# which blocks Laravel-routed asset URLs like /livewire-*/livewire.js and /flux/flux.js.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

if ! grep -qE '^APP_KEY=.+' .env; then
  php artisan key:generate --force --no-interaction
fi

echo "==> Publishing Livewire assets to public/vendor/livewire"
php artisan livewire:publish --assets --no-interaction

echo "==> Publishing Flux assets to public/flux"
mkdir -p public/flux
FLUX_SRC="vendor/livewire/flux/dist/flux-lite.min.js"
if [[ ! -f "$FLUX_SRC" ]]; then
  echo "Missing Flux asset: $FLUX_SRC" >&2
  exit 1
fi

cp "$FLUX_SRC" public/flux/flux.js
cp "$FLUX_SRC" public/flux/flux.min.js

echo "==> Frontend framework assets ready"
