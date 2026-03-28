#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$ROOT_DIR"
exec npx concurrently \
  --kill-others \
  --names "backend,frontend" \
  --prefix name \
  "bash ./scripts/dev_backend.sh" \
  "npm --prefix './web frontend' run dev -- --host 0.0.0.0 --port 5173"
