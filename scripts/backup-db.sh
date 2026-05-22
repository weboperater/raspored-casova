#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_PATH="${DB_PATH:-$ROOT_DIR/data/schedule.db}"
BACKUP_DIR="${BACKUP_DIR:-$ROOT_DIR/_backup/sqlite}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

if [[ ! -f "$DB_PATH" ]]; then
  echo "Database not found: $DB_PATH" >&2
  exit 1
fi

if ! command -v sqlite3 >/dev/null 2>&1; then
  echo "sqlite3 command is required" >&2
  exit 1
fi

mkdir -p "$BACKUP_DIR"

cat > "$ROOT_DIR/_backup/.htaccess" <<'EOF_HTACCESS'
Require all denied
EOF_HTACCESS

timestamp="$(date '+%Y%m%d-%H%M%S')"
tmp_db="$BACKUP_DIR/schedule-$timestamp.db"
archive="$tmp_db.gz"

sqlite3 "$DB_PATH" ".backup $tmp_db"
gzip -9 "$tmp_db"

find "$BACKUP_DIR" -type f -name 'schedule-*.db.gz' -mtime +"$RETENTION_DAYS" -delete

echo "$archive"
