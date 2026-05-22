#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "== PHP lint =="
find . -name '*.php' -not -path './vendor/*' -exec php -l {} \;

echo
echo "== SQLite health =="
scripts/check-db-health.sh

echo
echo "== Public render =="
scripts/check-public-render.sh

echo
echo "== Admin render =="
scripts/check-admin-render.sh

echo
echo "all checks passed"
