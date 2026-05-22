#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_PATH="${DB_PATH:-$ROOT_DIR/data/schedule.db}"
BACKUP_DIR="${BACKUP_DIR:-$ROOT_DIR/_backup/sqlite}"
CONFIRM_RESTORE="${CONFIRM_RESTORE:-}"
ARCHIVE="${1:-}"

if [[ -z "$ARCHIVE" ]]; then
  echo "Usage: CONFIRM_RESTORE=YES $0 /path/to/schedule-backup.db.gz" >&2
  echo "Optional: DB_PATH=/path/to/schedule.db BACKUP_DIR=/path/to/backups" >&2
  exit 2
fi

if [[ "$CONFIRM_RESTORE" != "YES" ]]; then
  echo "Refusing to restore without CONFIRM_RESTORE=YES" >&2
  exit 2
fi

if [[ ! -f "$ARCHIVE" ]]; then
  echo "Backup archive not found: $ARCHIVE" >&2
  exit 1
fi

case "$ARCHIVE" in
  *.db.gz) ;;
  *)
    echo "Backup archive must end with .db.gz" >&2
    exit 1
    ;;
esac

if ! command -v sqlite3 >/dev/null 2>&1; then
  echo "sqlite3 command is required" >&2
  exit 1
fi

if ! command -v gzip >/dev/null 2>&1; then
  echo "gzip command is required" >&2
  exit 1
fi

mkdir -p "$(dirname "$DB_PATH")" "$BACKUP_DIR"

timestamp="$(date '+%Y%m%d-%H%M%S')"
pre_restore="$BACKUP_DIR/pre-restore-$timestamp.db"
tmp_restore="$(mktemp "${TMPDIR:-/tmp}/raspored-restore.XXXXXX.db")"
trap 'rm -f "$tmp_restore"' EXIT

gzip -dc "$ARCHIVE" > "$tmp_restore"

if ! sqlite3 "$tmp_restore" 'PRAGMA integrity_check;' | grep -qx 'ok'; then
  echo "Backup integrity check failed: $ARCHIVE" >&2
  exit 1
fi

if [[ -f "$DB_PATH" ]]; then
  sqlite3 "$DB_PATH" ".backup $pre_restore"
  gzip -9 "$pre_restore"
  echo "pre-restore backup: $pre_restore.gz"
fi

sqlite3 "$tmp_restore" ".backup $DB_PATH"
echo "restored: $DB_PATH"
