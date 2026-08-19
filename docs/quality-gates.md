# UFSC quality gates

## Blocking checks

Every reliable change to the licence domain must pass:

- PHP syntax validation;
- JavaScript syntax validation;
- standalone/runtime regression suite;
- P0 licence regression suite (`composer repro:p0`);
- PHPUnit;
- PHPStan level 5 on the clean critical scope;
- WordPress Coding Standards security/database checks on the clean critical scope.

`composer quality` runs the reusable PHP quality gate locally or in CI.

## Why PHPStan/WPCS are progressive

The repository contains historical WordPress code that predates these tools. Enabling WPCS or PHPStan as a blocking check across every legacy file immediately would make every PR fail because of pre-existing debt and would force a large unrelated refactor, which is explicitly out of scope for the P0 licence stabilization.

The blocking scope therefore starts with components that are already clean and the new canonical finalization path:

- `inc/common/fighter-level.php`;
- `inc/common/licence-finalization-runtime.php`;
- `includes/core/class-ufsc-debug-trace.php`;
- `includes/core/class-ufsc-licence-finalization-service.php`;
- `includes/core/class-ufsc-season-service.php`.

Legacy components such as `inc/common/compliance.php`, `inc/common/licence-status.php`, `inc/woocommerce/cart-integration.php`, `includes/core/class-unified-handlers.php`, and `includes/core/class-ufsc-renewal-service.php` remain scanned by PHPStan when needed for symbol/type resolution and are covered by runtime/static regression tests. Their historical PHPCS/PHPStan debt must be reduced incrementally in dedicated changes, not silently ignored or mass-refactored during a payment/licence bug fix.

## WPCS warnings

Warnings do not fail the gate. Direct database access warnings are expected in small domain services that intentionally perform atomic reads/writes and advisory-lock workflows. SQL identifiers and values must still use prepared statements; security errors remain blocking.

## Rule for future changes

When a legacy file becomes clean under the configured checks, add it to the blocking `paths` / `<file>` lists. Do not remove a clean file from the gate to make CI pass.
