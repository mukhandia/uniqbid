#!/usr/bin/env bash
set -euo pipefail

# Smoke-test a deployed site: homepage + published Livewire/Flux static assets.

APP_URL="${1:?APP_URL required}"
APP_URL="${APP_URL%/}"

curl_status() {
  local url="$1"
  curl -sS -o /dev/null -w "%{http_code}" \
    --connect-timeout 15 \
    --max-time 45 \
    -L \
    "$url" 2>/dev/null || echo "000"
}

retry_url() {
  local label="$1"
  local url="$2"
  shift 2
  local acceptable=("$@")
  local code="000"

  for attempt in 1 2 3 4 5; do
    code="$(curl_status "$url")"

    for expected in "${acceptable[@]}"; do
      if [ "$code" = "$expected" ]; then
        echo "$label OK (HTTP $code)"
        return 0
      fi
    done

    echo "$label attempt $attempt failed (HTTP $code) — retrying..."
    sleep 5
  done

  echo "$label failed after retries (last HTTP $code)"
  return 1
}

retry_url "Homepage" "$APP_URL" "200" "302"

for asset in "/flux/flux.js" "/vendor/livewire/manifest.json"; do
  retry_url "Frontend asset $asset" "${APP_URL}${asset}" "200" || exit 1
done

echo "Livewire and Flux assets OK"
