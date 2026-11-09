#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
CONFIG_FILE="${1:-$ROOT_DIR/AI_AGENT_SETUP/config/local.json}"

if [ ! -f "$CONFIG_FILE" ]; then
  echo "Missing $CONFIG_FILE" >&2
  echo "Copy AI_AGENT_SETUP/config/local.example.json to config/local.json and fill in local values." >&2
  exit 1
fi

APP_DIR="$(find "$ROOT_DIR/DTLive v2" -type d -path '*/Mainfiles_DTLive/Appcode/yourappname' -print -quit)"
if [ -z "$APP_DIR" ]; then
  echo "Flutter app directory was not found." >&2
  exit 1
fi

cd "$APP_DIR"
flutter pub get
flutter build web --release --dart-define-from-file="$CONFIG_FILE"
