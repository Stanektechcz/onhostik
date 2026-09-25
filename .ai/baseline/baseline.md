# Baseline — `edb635b`, the stack tip of TASK-0027 (2026-09-25)

Measured by TASK-0027 with `.\brain.ps1 gate -Task TASK-0027` in its clean worktree (0 uncommitted paths), on the stack
`development @ 2426c17` + TASK-0017 … TASK-0027 + cherry-picked TASK-0018 and TASK-0003, before the docs commits of
TASK-0027 (they change no code). Machine-readable: `baseline.json`. Previous baseline: `8e4614a` on `development`
(2026-09-23, TASK-0001: Pest 856 / 14 646, 532 routes, 59 migrations).

| Check | Result |
| --- | --- |
| Gate (full: Pint, Larastan, Pest, frontend build) | **PASS**, no regressions, no pre-existing failures (`.ai/reports/TASK-0027-gate.md`) |
| Pest (`php artisan test --compact`) | **PASS** 1 364/1 364, 18 258 assertions, 363 s, 337 test files |
| Larastan level 5 | **PASS** 0 errors (641 baseline entries / 1 013 suppressed occurrences = typing debt, unchanged) |
| Pint | **PASS** |
| Vite build | **PASS** |
| `composer validate` / `composer audit` / `npm audit` | **PASS** / 0 advisories / 0 vulnerabilities |
| App boot (`artisan about`) | **PASS** Laravel 13.30.1, PHP 8.3.33 |
| Routes | 543 registered, 493 under `/v1` |
| Migrations on an empty SQLite file | **PASS** 61 ran, 0 pending |
| `scripts/ai/tests/Orchestration.Tests.ps1` | **PASS** |
| `scripts/ai/tests/Config.Tests.ps1` | **PREEXISTING FAILURE** — checks Brain config that only exists on `origin/chore/onhost-brain` |
| E2E, Pest on PostgreSQL | NOT RUN locally (CI only; `pest-postgres` ran green on the pushed TASK-0017/0018/0019/0003 branches, not on the stack) |

## Pre-existing problems (not caused by any task)

1. `Config.Tests.ps1` (see table).
2. `scripts/ai/test.ps1` labels reports with `origin/development` instead of the tested revision.

The order-dependent `RenewalGuardTest` failure of the previous baseline is fixed (TASK-0018: a global hook in
`tests/Pest.php` restores the process environment after every test); `known_failing_tests` is empty.

## How to use it

`brain.ps1 gate` compares every failing test with `known_failing_tests`. When `development` moves, the baseline stays
valid as the reference until someone re-measures it on purpose (update `revision`, counts and lists together). Once the
stack is merged, re-measure on the merge commit of `development`.
