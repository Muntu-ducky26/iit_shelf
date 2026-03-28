#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "Installing web frontend dependencies..."
npm --prefix "$ROOT_DIR/web frontend" install

echo "All dependencies installed."
