#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if command -v systemctl >/dev/null 2>&1; then
  if ! systemctl is-active --quiet mariadb; then
    echo "Starting MariaDB..."
    sudo systemctl start mariadb || true
  fi
fi

echo "Starting backend on http://127.0.0.1:8000"
cd "$ROOT_DIR/backend"
exec php -S 0.0.0.0:8000 router.php
