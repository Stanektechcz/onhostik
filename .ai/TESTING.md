# Testing

## Commands (from a worktree root; PHP is `C:/Users/medion/php83/php.exe`)

| Purpose | Command |
| --- | --- |
| One file / focused | `php artisan test --compact tests/Feature/Orders/CheckoutTest.php` (several paths allowed) |
| Suite | `php artisan test --compact [--testsuite=Unit|Feature|Contract]` |
| Critical flows + style | `.\brain.ps1 gate -Quick` |
| Full gate with baseline comparison | `.\brain.ps1 gate [-Task TASK-NNNN]` (Pint, Larastan, Pest, Vite build) |
| Style / static analysis | `vendor/bin/pint --test` · `vendor/bin/phpstan analyse --memory-limit=2G` |
| Column widths (before release, after migrations/key formats) | `ONHOST_WIDTH_GUARD=1 php artisan test` |
| E2E | `npm run e2e` (needs the app running; CI `e2e.yml`) |

`brain.ps1 test` (the Brain's older runner) runs the same tools without baseline comparison and rewrites
`docs/generated/TEST_STATUS.md`; use `gate` for task evidence.

## Baseline

`.ai/baseline/baseline.json`: 856/856 Pest tests, Larastan 0 errors, Pint/build/audits clean at `8e4614a`.
`known_failing_tests` lists failures that exist without any task (today: one order-dependent test, explained in
`known_failing_notes`). The gate classifies every failure: in the list → PREEXISTING, otherwise → REGRESSION.
Add to the list only with proof (the same failure at the base revision), remove as soon as it is fixed.

## Pyramid in this repository

- **Unit** (`tests/Unit`): pure rules (money, directives, parsing).
- **Feature** (`tests/Feature/<Domain>`): HTTP + bus + DB + queue (sync) per domain; the bulk of the suite.
- **Contract** (`tests/Contract`): every provider adapter against recorded vendor answers via `Http::fake()`.
- **E2E** (`e2e/`): surfaces smoke only. Do not add browser tests for details a feature test can prove.

## Rules that bite here

- Test first for a significant change: write the failing test, see it fail for the right reason, then implement.
- Never weaken a legitimate test to pass. If a test is wrong, prove why and change it as a deliberate contract change.
- Pest files share one global function namespace: helper names must be unique, shared fixtures go to `tests/Pest.php`.
- `Http::fake()` callbacks stack (first non-null wins): switch behaviour with a by-reference flag.
- `withHeader('Idempotency-Key', …)` sticks for the rest of the test: use a fresh key per write.
- Tests that set `$_ENV`/`putenv()` credentials must remove them (`afterEach`), or later tests in the process change
  behaviour (see the RenewalGuardTest note in the baseline).
- The app runs in UTC; after touching time-driven code run the suite once inside 00:00–06:00 UTC.
- SQLite hides PostgreSQL errors (VARCHAR widths, aborted transactions, `sum()` as string): CI `pest-postgres` decides.
- ISPConfig actions are asynchronous: drive them with `driveOperation()`; tests call `relayPending()` for the outbox.

## What counts as verified

VERIFIED = a command ran and its output shows the claim. ASSUMED = reasoning or reading code only. NOT TESTED = no
evidence. Reports and task files keep the three apart.
