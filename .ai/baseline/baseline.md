# Baseline — `8e4614a` on `development` (2026-09-23)

Measured by TASK-0001 in a clean worktree (no uncommitted changes). Machine-readable: `baseline.json`.

| Check | Result |
| --- | --- |
| Pest (`php artisan test --compact`) | **PASS** 856/856, 14 646 assertions, 188 s |
| Larastan level 5 | **PASS** 0 errors (641 baseline entries / 1 013 suppressed occurrences = typing debt) |
| Pint | **PASS** |
| Vite build | **PASS** |
| `composer validate` / `composer audit` / `npm audit` | **PASS** / 0 advisories / 0 vulnerabilities |
| App boot (`artisan about`) | **PASS** Laravel 13.30.1, PHP 8.3.33 |
| Routes | 532 registered, 481 under `/v1` |
| Migrations on an empty SQLite file | **PASS** 59 ran, 0 pending |
| `scripts/ai/tests/Config.Tests.ps1` | **PREEXISTING FAILURE** — checks Brain config that only exists on `origin/chore/onhost-brain` |
| E2E, Pest on PostgreSQL | NOT RUN locally (CI only) |

## Pre-existing problems (not caused by any task)

1. `tests/Feature/Billing/RenewalGuardTest.php` fails when `tests/Feature/Orders/CheckoutTest.php` runs earlier in
   the same process: CheckoutTest leaves Comgate credentials in `$_ENV`/`putenv`. Invisible in the full suite
   (alphabetical order), visible in `gate -Quick`. Proven by running both orders. Listed in `known_failing_tests`.
2. `Config.Tests.ps1` (see table).
3. `scripts/ai/test.ps1` labels reports with `origin/development` instead of the tested revision.

## How to use it

`brain.ps1 gate` compares every failing test with `known_failing_tests`. When `development` moves, the baseline stays
valid as the reference until someone re-measures it on purpose (update `revision`, counts and lists together).
