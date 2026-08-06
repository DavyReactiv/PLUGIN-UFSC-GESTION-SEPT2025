#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

phpunit_tests=()
standalone_tests=()
while IFS= read -r -d '' test_file; do
  if grep -Eq 'PHPUnit\\Framework\\TestCase|extends[[:space:]]+(WP_UnitTestCase|TestCase)|use[[:space:]]+PHPUnit\\Framework\\TestCase' "$test_file"; then
    phpunit_tests+=("$test_file")
  else
    standalone_tests+=("$test_file")
  fi
done < <(find tests -maxdepth 1 -name 'test-*.php' -type f -print0 | sort -z)

echo "Standalone scripts: ${#standalone_tests[@]}"
for test_file in "${standalone_tests[@]}"; do php "$test_file"; done

echo "PHPUnit tests detected: ${#phpunit_tests[@]}"
if ((${#phpunit_tests[@]})); then
  if [[ -x vendor/bin/phpunit ]]; then
    echo "PHPUnit: executing via vendor/bin/phpunit"
    vendor/bin/phpunit -c phpunit.xml
  else
    echo "PHPUnit unavailable: vendor/bin/phpunit not found."
  fi
fi

echo "Standalone test suite completed successfully."
