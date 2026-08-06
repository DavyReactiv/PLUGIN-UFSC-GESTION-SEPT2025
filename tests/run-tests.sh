#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

phpunit_tests=()
standalone_tests=()
while IFS= read -r -d '' test_file; do
  if rg -q 'extends[[:space:]]+(WP_UnitTestCase|TestCase)|PHPUnit\\Framework' "$test_file"; then
    phpunit_tests+=("$test_file")
  else
    standalone_tests+=("$test_file")
  fi
done < <(find tests -maxdepth 1 -name 'test-*.php' -type f -print0 | sort -z)

echo "Standalone scripts: ${#standalone_tests[@]}"
for test_file in "${standalone_tests[@]}"; do php "$test_file"; done

if ((${#phpunit_tests[@]})); then
  if [[ -x vendor/bin/phpunit ]]; then
    echo "PHPUnit: executing ${#phpunit_tests[@]} test file(s)"
    vendor/bin/phpunit
  else
    echo "PHPUnit: unavailable; ${#phpunit_tests[@]} PHPUnit test file(s) detected." >&2
    echo "Install a compatible vendor/bin/phpunit to execute this suite."
  fi
else
  echo "PHPUnit: no PHPUnit test files detected."
fi
