#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"

if [ ! -d "$ROOT_DIR/.git" ]; then
  echo "Not a Git working copy: $ROOT_DIR" >&2
  exit 1
fi

if [ -n "$(git -C "$ROOT_DIR" status --porcelain)" ]; then
  echo "Uncommitted changes exist. Save or commit them before pulling." >&2
  git -C "$ROOT_DIR" status --short
  exit 2
fi

git -C "$ROOT_DIR" fetch origin main
git -C "$ROOT_DIR" pull --ff-only origin main
git -C "$ROOT_DIR" status --short --branch
