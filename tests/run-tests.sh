#!/usr/bin/env bash
set -euo pipefail
script_dir=${0%/*}
if [[ "$script_dir" == "$0" ]]; then script_dir=.; fi
cd "$script_dir/.."

phpunit_tests=()
standalone_tests=()
shopt -s nullglob
for test_file in tests/test-*.php; do
  if php -r '$s=file_get_contents($argv[1]); exit(preg_match("/(PHPUnit\\\\Framework\\\\TestCase|extends[[:space:]]+(WP_UnitTestCase|TestCase)|use[[:space:]]+PHPUnit\\\\Framework\\\\TestCase)/",$s)?0:1);' "$test_file"; then
    phpunit_tests+=("$test_file")
  else
    standalone_tests+=("$test_file")
  fi
done

echo "Standalone scripts: ${#standalone_tests[@]}"
for test_file in "${standalone_tests[@]}"; do php "$test_file"; done

echo "PHPUnit tests detected: ${#phpunit_tests[@]}"
if ((${#phpunit_tests[@]})); then
  if [[ -f vendor/bin/phpunit ]]; then
    echo "PHPUnit: executing via vendor/bin/phpunit"
    php vendor/bin/phpunit -c phpunit.xml
  else
    echo "PHPUnit unavailable: vendor/bin/phpunit not found."
  fi
fi

runtime_js=(tests/test-*.js)
echo "Node runtime scripts: ${#runtime_js[@]}"
for test_file in "${runtime_js[@]}"; do node "$test_file"; done

echo "PHP and Node runtime suites completed successfully."
