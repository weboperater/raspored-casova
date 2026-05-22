#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

pages=(
  "index.php:Admin Login"
  "dashboard.php:Sistemsko stanje"
  "schedule.php:Uredi Raspored"
  "periods.php:Termini"
  "subjects.php:Novi predmet"
  "viber.php:Sačuvani šabloni"
  "audit.php:Istorija izmena"
  "help.php:Uputstvo"
)

for entry in "${pages[@]}"; do
  page="${entry%%:*}"
  needle="${entry#*:}"

  php -d display_errors=1 -r '
    $page = $argv[1];
    $needle = $argv[2];
    $sessionId = "render" . preg_replace("/[^A-Za-z0-9-]/", "", $page);

    session_id($sessionId);
    session_start();
    if ($page === "index.php") {
        $_SESSION = [];
    } else {
        $_SESSION["admin_id"] = 1;
        $_SESSION["admin_user"] = "render-test";
    }
    $_SERVER["PHP_SELF"] = "/admin/" . $page;
    $_SERVER["REQUEST_METHOD"] = "GET";
    $_GET = [];
    if ($page === "schedule.php") {
        $_GET["week"] = "A";
    }

    ob_start();
    include "admin/" . $page;
    $html = ob_get_clean();
    session_write_close();

    if (!str_contains($html, $needle)) {
        fwrite(STDERR, $page . " render failed\n");
        exit(1);
    }
    echo $page . " render ok\n";
  ' "$page" "$needle"
done
