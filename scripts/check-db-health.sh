#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_PATH="${DB_PATH:-$ROOT_DIR/data/schedule.db}"
failures=0

if [[ ! -f "$DB_PATH" ]]; then
  echo "fail: database not found: $DB_PATH"
  exit 1
fi

if ! command -v sqlite3 >/dev/null 2>&1; then
  echo "fail: sqlite3 command is required"
  exit 1
fi

check_equals() {
  local label="$1"
  local actual="$2"
  local expected="$3"
  if [[ "$actual" == "$expected" ]]; then
    echo "ok: $label = $actual"
  else
    echo "fail: $label expected $expected, got $actual"
    failures=$((failures + 1))
  fi
}

check_positive() {
  local label="$1"
  local actual="$2"
  if [[ "$actual" =~ ^[0-9]+$ && "$actual" -gt 0 ]]; then
    echo "ok: $label = $actual"
  else
    echo "fail: $label must be greater than 0, got $actual"
    failures=$((failures + 1))
  fi
}

query_scalar() {
  sqlite3 -batch -noheader "$DB_PATH" "$1"
}

integrity="$(query_scalar 'PRAGMA integrity_check;')"
check_equals "SQLite integrity_check" "$integrity" "ok"

required_tables=(
  settings
  admins
  periods
  subjects
  schedule
  viber_templates
  login_attempts
  admin_audit_log
)

for table in "${required_tables[@]}"; do
  exists="$(query_scalar "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='$table';")"
  check_equals "table $table exists" "$exists" "1"
done

admin_count="$(query_scalar 'SELECT COUNT(*) FROM admins;')"
period_count="$(query_scalar 'SELECT COUNT(*) FROM periods;')"
subject_count="$(query_scalar 'SELECT COUNT(*) FROM subjects;')"
schedule_a_count="$(query_scalar "SELECT COUNT(*) FROM schedule WHERE week_type='A';")"
schedule_b_count="$(query_scalar "SELECT COUNT(*) FROM schedule WHERE week_type='B';")"

check_positive "admins" "$admin_count"
check_positive "periods" "$period_count"
check_positive "subjects" "$subject_count"
check_positive "schedule week A rows" "$schedule_a_count"
check_positive "schedule week B rows" "$schedule_b_count"

invalid_schedule_refs="$(query_scalar '
  SELECT COUNT(*)
  FROM schedule s
  LEFT JOIN subjects sub ON sub.id = s.subject_id
  WHERE s.subject_id IS NOT NULL AND sub.id IS NULL;
')"
check_equals "invalid schedule subject references" "$invalid_schedule_refs" "0"

if [[ "$failures" -gt 0 ]]; then
  echo "database health check failed: $failures issue(s)"
  exit 1
fi

echo "database health check passed"
