#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
WORKER_COUNT="${MEDIA_QUEUE_WORKERS:-3}"
LOCK_FILE="$APP_DIR/storage/framework/media-queue-worker.lock"
CLEANUP_LOCK_FILE="$APP_DIR/storage/framework/media-staging-cleanup.lock"
CLEANUP_INTERVAL_SECONDS="${MEDIA_STAGING_CLEANUP_INTERVAL_SECONDS:-300}"
STAGING_TTL_SECONDS="${MEDIA_STAGING_TTL_SECONDS:-3600}"
STAGING_ACTIVE_TTL_SECONDS="${MEDIA_STAGING_ACTIVE_TTL_SECONDS:-86400}"

cd "$APP_DIR"
mkdir -p "$(dirname "$LOCK_FILE")"
exec 9>"$LOCK_FILE"
flock -n 9 || exit 0

worker_loop() {
  local last_cleanup=0
  while true; do
    now="$(date +%s)"
    if (( now - last_cleanup >= CLEANUP_INTERVAL_SECONDS )); then
      exec 8>"$CLEANUP_LOCK_FILE"
      if flock -n 8; then
        "$PHP_BIN" artisan media:cleanup-staging \
          --older-than="$STAGING_TTL_SECONDS" \
          --active-max-age="$STAGING_ACTIVE_TTL_SECONDS" \
          >> "$APP_DIR/storage/logs/media-staging-cleanup.log" 2>&1 || true
      fi
      exec 8>&-
      last_cleanup="$now"
    fi

    "$PHP_BIN" artisan queue:work database \
      --queue=media-video \
      --sleep=1 \
      --timeout=21600 \
      --tries=3 \
      --backoff=30 \
      --memory=256 \
      --max-time=3600 || true
    sleep 2
  done
}

for index in $(seq 1 "$WORKER_COUNT"); do
  worker_loop &
done

wait
