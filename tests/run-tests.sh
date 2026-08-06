#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"
phpunit=()
autonomous=()
while IFS= read -r -d '' file; do
  if rg -q 'PHPUnit|TestCase' "$file"; then phpunit+=("$file"); else autonomous+=("$file"); fi
done < <(find tests -maxdepth 1 -name 'test-*.php' -type f -print0)
for file in "${autonomous[@]}"; do echo "[autonomous] $file"; php "$file"; done
if ((${#phpunit[@]})); then
  if [[ -x vendor/bin/phpunit ]]; then echo '[phpunit] phpunit.xml'; vendor/bin/phpunit -c phpunit.xml; else echo "[phpunit] skipped: vendor/bin/phpunit unavailable (${#phpunit[@]} files)" >&2; fi
fi
