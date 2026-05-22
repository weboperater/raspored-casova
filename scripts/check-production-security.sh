#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-}"
HOST_HEADER="${HOST_HEADER:-}"

if [[ -z "$BASE_URL" ]]; then
  echo "Usage: $0 https://example.com" >&2
  echo "Optional: HOST_HEADER=example.local $0 https://127.0.0.1" >&2
  exit 2
fi

BASE_URL="${BASE_URL%/}"
failures=0

curl_args=(-k -L -sS -o /dev/null -D -)
if [[ -n "$HOST_HEADER" ]]; then
  curl_args+=(-H "Host: $HOST_HEADER")
fi

fetch_headers() {
  local url="$1"
  local out_file="$2"
  curl "${curl_args[@]}" "$url" > "$out_file"
}

check_header() {
  local headers="$1"
  local header_name="$2"
  if printf '%s\n' "$headers" | grep -iq "^$header_name:"; then
    echo "ok: header $header_name"
  else
    echo "fail: missing header $header_name"
    failures=$((failures + 1))
  fi
}

check_status_not_200() {
  local path="$1"
  local label="$2"
  local status_file status
  status_file="$(mktemp)"
  curl "${curl_args[@]}" -w '%{http_code}' "$BASE_URL$path" > "$status_file"
  status="$(tail -n 1 "$status_file")"
  rm -f "$status_file"
  if [[ "$status" == "200" ]]; then
    echo "fail: $label returned 200 ($path)"
    failures=$((failures + 1))
  else
    echo "ok: $label blocked with HTTP $status"
  fi
}

if [[ "$BASE_URL" != https://* ]]; then
  echo "fail: base URL must use HTTPS"
  failures=$((failures + 1))
fi

root_headers_file="$(mktemp)"
admin_headers_file="$(mktemp)"
trap 'rm -f "$root_headers_file" "$admin_headers_file"' EXIT

fetch_headers "$BASE_URL/" "$root_headers_file"
fetch_headers "$BASE_URL/admin/" "$admin_headers_file"

root_headers="$(cat "$root_headers_file")"
admin_headers="$(cat "$admin_headers_file")"

check_header "$root_headers" "Strict-Transport-Security"
check_header "$root_headers" "X-Content-Type-Options"
check_header "$root_headers" "X-Frame-Options"
check_header "$root_headers" "Referrer-Policy"
check_header "$admin_headers" "Cache-Control"

if printf '%s\n' "$admin_headers" | grep -iq '^Cache-Control:.*no-store'; then
  echo "ok: admin Cache-Control contains no-store"
else
  echo "fail: admin Cache-Control does not contain no-store"
  failures=$((failures + 1))
fi

check_status_not_200 "/data/schedule.db" "SQLite database"
check_status_not_200 "/config/env.php" "env config"
check_status_not_200 "/.git/config" "git metadata"
check_status_not_200 "/_backup/" "backup folder"

if [[ "$failures" -gt 0 ]]; then
  echo "security check failed: $failures issue(s)"
  exit 1
fi

echo "security check passed"
