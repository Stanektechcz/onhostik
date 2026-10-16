# Testing

## Commands (from a worktree root; PHP is `C:/Users/medion/php83/php.exe`)

| Purpose | Command |
| --- | --- |
| One file / focused | `php artisan test --compact tests/Feature/Orders/CheckoutTest.php` (several paths allowed) |
| Suite | `php artisan test --compact [--testsuite=Unit|Feature|Contract]` |
| Inner loop (local habit, never evidence) | `php artisan test --compact --dirty` runs only test files with uncommitted changes; finish with the suite or the gate, CI always runs all shards |
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
- **Travel first, seed after** (TASK-0192): seeded prices are dated `now()->subDay()`, so a test that seeds and then travels to a
  fixed day breaks once the real clock passes that day. `travelTo` is the first line of `beforeEach`. Sweep for such time bombs
  with `ONHOST_TEST_CLOCK=2027-04-01 php artisan test --parallel` (tests/TestCase.php shifts the clock, still ticking). Known false
  alarms of the sweep (TASK-0201, 2026-10-13: 16 of 3 163): code that mixes the shifted clock with a real one — database default
  timestamps, file mtimes, `time()`, certificates signed by openssl, `travelBack()` inside `clockSweep()` — in CustomIso*,
  DeployGate, IdempotencyScope, CertificateExpiry, MarketplaceSla, Partner*, PayoutSafety, PlanChange, CreditOrderApprovalStale,
  LimitRaise/Withdrawal clock sweeps and RiskFloor. A new failure outside that list that names a fixed date is a time bomb.
- SQLite hides PostgreSQL errors (VARCHAR widths, aborted transactions, `sum()` as string): CI `pest-postgres` decides.
- ISPConfig actions are asynchronous: drive them with `driveOperation()`; tests call `relayPending()` for the outbox.

## What counts as verified

VERIFIED = a command ran and its output shows the claim. ASSUMED = reasoning or reading code only. NOT TESTED = no
evidence. Reports and task files keep the three apart.

## Cloud sandbox (Linux)

Claude Code cloud sessions run on Linux without PowerShell, so `brain.ps1` does not run there.

- **SessionStart hook** (`.claude/settings.json` → `scripts/ai/setup-sandbox.sh`): does nothing unless
  `CLAUDE_CODE_REMOTE=true`. In the sandbox it checks every `ext-*` of `composer.json` against `php -m`, installs a
  missing `php8.3-bcmath` with apt as root (`apt-get update` only after a failed install), and otherwise fails naming
  the missing extension (never `--ignore-platform-req`). Then `composer install` unless `vendor/` is current with
  `composer.lock`; a failing scripts step is retried with `--no-scripts` plus a warning. It never creates or reads
  `.env`, never runs `key:generate`/`migrate`, never calls panels: `phpunit.xml` carries the test env and `APP_KEY`.
  A second start skips everything within seconds.
- **`./brain.sh gate [--quick] [tests…]`**: Pint, Larastan and the PHPStan baseline ratchet (not with `--quick`),
  Pest with a JUnit report (with `--quick` only `critical_flows[].tests` of the baseline plus the given paths), then
  `php scripts/ci/gate-classify.php` sorts failures into REGRESSION / PREEXISTING against
  `.ai/baseline/baseline.json`; a missing or empty JUnit report is a FAIL. Prints `VERDICT PASS|FAIL` and the steps.
  `--format=json` (W5-3; default `text`, byte-identical to before) prints one object
  `{"verdict","regression","preexisting","evidence":"junit|missing-junit|no-failure-named","pest_exit"}` on stdout
  with the same exit codes 0/1/2; argument and baseline errors stay on stderr with exit 2 and empty stdout.
  No frontend build here; `gate.ps1` stays the reference gate until it uses `gate-classify.php` too.
- **SQLite vs CI**: the sandbox runs Pest on SQLite `:memory:` (phpunit.xml) like Windows; PostgreSQL 16 runs only in
  CI job `pest-postgres`. A sandbox PASS says nothing about PostgreSQL-specific behaviour.
- Without `vendor/` (hook not run or failed) none of this runs: report Pest/Pint/Larastan as **NOT TESTED**, with
  `php -l` as the only evidence, never as VERIFIED.
