#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_PATH="${DB_PATH:-$ROOT_DIR/data/schedule.db}"
BACKUP_DIR="${BACKUP_DIR:-$ROOT_DIR/_backup/sqlite}"

if [[ ! -f "$DB_PATH" ]]; then
  echo "Database not found: $DB_PATH" >&2
  exit 1
fi

if ! command -v sqlite3 >/dev/null 2>&1; then
  echo "sqlite3 command is required" >&2
  exit 1
fi

query_scalar() {
  sqlite3 -batch -noheader "$DB_PATH" "$1"
}

integrity="$(query_scalar 'PRAGMA integrity_check;')"
if [[ "$integrity" != "ok" ]]; then
  echo "Database integrity check failed: $integrity" >&2
  exit 1
fi

before_login_attempts="$(query_scalar 'SELECT COUNT(*) FROM login_attempts;')"
before_audit_logs="$(query_scalar 'SELECT COUNT(*) FROM admin_audit_log;')"
backup_path="$(DB_PATH="$DB_PATH" BACKUP_DIR="$BACKUP_DIR" "$ROOT_DIR/scripts/backup-db.sh")"

sqlite3 "$DB_PATH" <<'SQL'
BEGIN IMMEDIATE;
DELETE FROM login_attempts;
DELETE FROM admin_audit_log;
COMMIT;
VACUUM;
SQL

after_login_attempts="$(query_scalar 'SELECT COUNT(*) FROM login_attempts;')"
after_audit_logs="$(query_scalar 'SELECT COUNT(*) FROM admin_audit_log;')"

echo "backup: $backup_path"
echo "login_attempts: $before_login_attempts -> $after_login_attempts"
echo "admin_audit_log: $before_audit_logs -> $after_audit_logs"
