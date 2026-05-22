#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

checks=(
  "default::Raspored"
  "week-a:A:Nedelja A"
  "week-b:B:Nedelja B"
)

for entry in "${checks[@]}"; do
  name="${entry%%:*}"
  rest="${entry#*:}"
  week="${rest%%:*}"
  needle="${rest#*:}"

  php -d display_errors=1 -r '
    $week = $argv[1];
    $needle = $argv[2];
    $_SERVER["REQUEST_METHOD"] = "GET";
    $_SERVER["PHP_SELF"] = "/index.php";
    $_GET = [];
    if ($week !== "") {
        $_GET["week"] = $week;
    }

    ob_start();
    include "index.php";
    $html = ob_get_clean();

    if (!str_contains($html, $needle) || !str_contains($html, "Ponedeljak")) {
        fwrite(STDERR, "public render failed\n");
        exit(1);
    }
    echo "public render ok\n";
  ' "$week" "$needle" | sed "s/^/$name: /"
done
