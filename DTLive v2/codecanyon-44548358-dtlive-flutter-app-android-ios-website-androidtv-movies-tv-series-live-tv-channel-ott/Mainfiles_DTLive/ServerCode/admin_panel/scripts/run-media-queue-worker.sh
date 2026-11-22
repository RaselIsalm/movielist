#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
WORKER_COUNT="${MEDIA_QUEUE_WORKERS:-3}"
LOCK_FILE="$APP_DIR/storage/framework/media-queue-worker.lock"

cd "$APP_DIR"
mkdir -p "$(dirname "$LOCK_FILE")"
exec 9>"$LOCK_FILE"
flock -n 9 || exit 0

worker_loop() {
  while true; do
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
