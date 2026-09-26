# Release and rollback

The control plane runs in place on the aaPanel host (`docs/runbooks/deploy-aapanel.md`): one checkout, PHP-FPM, the queue
workers and the scheduler as systemd units. A release is therefore a short **maintenance window** (503) and is done only by
the gated deployer `/usr/local/sbin/onhost-deploy` (`infra/aapanel/deploy.sh`, TASK-0032). Until 2026-09-27 the script
restarted the workers on the new code first and then ran `onhost:doctor || true`, so nothing the doctor found could stop a
release, and the backup it took was never verified (onboarding audit C12/C14).

## Before a release

* `php artisan test` green; contract tests for every adapter you touched; `vendor/bin/phpstan analyse` and
  `composer audit` clean (both run in CI).
* **The `tests` workflow is green on the commit you release — including `pest-postgres` and `deploy-scripts`.** The local suite runs on SQLite,
  and SQLite forgives what the production database refuses: a VARCHAR that is too long, a statement after a failed one in
  the same transaction. The workflow was red for 92 runs in a row (2026-09-15 → 2026-09-20) and nobody looked: a manual
  credit by staff put its 81-character idempotency key into a 60-character ledger column and would have answered 500 in
  production. `gh run list --branch development --workflow tests --limit 3` after every push;
  `tests/Feature/Platform/DeclaredColumnWidthTest.php` measures stored values against the widths the migrations declare;
  `ONHOST_WIDTH_GUARD=1 php artisan test` does it after EVERY test of the suite (`tests/WidthGuard.php`, about half a minute
  more) and writes what is too long to `storage/framework/testing/width-guard.log` — run it before a release and whenever a
  migration or the format of a key changes.
* A release record exists: `/ai-release-check <from>..<sha>` writes `.ai/releases/<date>-<sha7>.md` (format and tag rules:
  `.ai/releases/README.md`). Production releases only an annotated tag the **owner** created and signed with SSH.
* The backup is not a separate manual step any more: the deployer takes `onhost:platform:backup` **inside the window**
  and refuses to switch the code unless `onhost:platform:backup:verify <that set>` answers `OK <set>` (exit 3 otherwise).
* Error-budget policy for `portal`/`payments` is not `freeze` (`GET /v1/staff/reports/slo`). A freeze blocks
  releases except fixes for the incident that caused it.
* Migrations are additive (new tables/columns, no drops of data in the same release); destructive changes ship
  one release after the code stopped using the column.
* No release between 02:00 and 03:00 (the nightly platform backup at 02:15 would fall into the window and be skipped).

## Deploy (what `onhost-deploy` does, in this order)

```bash
REF=<tag|sha> EXPECTED_SHA=<40-hex sha> DEPLOY_OPERATOR=<name> PHP_FPM_RELOAD='<reload command>' /usr/local/sbin/onhost-deploy; echo rc=$?
```

| Stage | What happens | On failure |
| --- | --- | --- |
| preflight | lock (`flock`); `REF` and the full `EXPECTED_SHA` required, `BRANCH` refused; APP_ENV read fail-closed (only `staging`, `local`, `testing` are not production); APP_URL host = `SITE`; `.git` root-owned and not group/world-writable; `git fetch --tags`; REF resolved (production: an annotated tag verified with `git verify-tag` against the root-owned `allowed_signers`); the target must not carry a newer deployer than the installed one; tree clean (except the generated `contracts/openapi/onhost-v1.yaml` and `VERSION`); `SKIP_BACKUP`/`ALLOW_DOCTOR_FAIL` validated and bound to the target SHA; `/up` reachable over loopback (401/403 = the vhost's basic auth is not bypassed for loopback) | exit **2**, nothing changed |
| drain | the active `onhost-queue@*` and `onhost-scheduler` units are stopped and waited for (`DRAIN_TIMEOUT`, default 300 s; a worker finishes its job first); the list is kept in `drained-units` | exit **3**, the units are started again |
| down | `artisan down --retry=60 --with-secret` (output discarded — the bypass URL never reaches a terminal or log) | exit 3, units started |
| backup | `onhost:platform:backup`, the `Set …` line of THIS run, `onhost:platform:backup:verify <set>` must print `OK <set>`; the whole output (with the sha256 prefixes) stays in `runs/<ts>/backup.out` | exit **3**, nothing switched, the site back up if this run took it down |
| switch + build | `git checkout -f --detach <sha>` and a clean-tree check; bootstrap caches removed; `composer install --no-dev`; `migrate --force --isolated` with `lock_timeout=10s` (no retry); `AuthorizationSeeder`, `NotificationTemplateSeeder`; `config:cache`, `route:cache`, `event:cache`; `onhost:openapi` (a failure is a WARN); `VERSION`; storage/bootstrap ownership repaired; `PHP_FPM_RELOAD` | exit **4** |
| gate | storage and bootstrap/cache owned by the PHP-FPM user; `onhost:doctor --json` judged by `deploy-gate.php` (below); `GET /up` and `GET /v1/status` must answer 200 through the maintenance bypass | exit **5** |
| live | the expected staging freeze re-asserted (`expect-freeze`), the drained units started, `artisan up` — only when the deployer took the site down (a site an operator took down stays down); public `GET /up` must answer 200 | exit **6** after `up` |
| record | `last-good.json` (sha, ref, tag, time, operator) and one line in `deploy.log` (from, to, stage, rc, backup set, drain seconds, window seconds, override) | — |

State lives in `/var/lib/onhost-deploy/<site>/` (root, 0700): `deploy.log`, `last-good.json`, `drained-units`,
`down-by-deploy`, `expect-freeze`, `allowed_signers`, `gitconfig` and `runs/<ts>-<sha12>/` (`backup.out`,
`report.json`, `verdict.out`, `tag.txt`).

### The gate (`infra/aapanel/deploy-gate.php`)

The installed helper — not the target — owns the lists, so a release cannot rename or drop a row to pass, and a
rollback target is judged by today's rules. Rows are matched by `area|check`; any status other than `OK`, or a missing
row, counts as failed (outside production a blocking row is `WARN`, not `FAIL`: the status alone would not stop it).

* **HARD — never overridable:** `app|APP_DEBUG off`, `app|APP_KEY set`, `storage|database reachable`,
  `storage|storage/app writable`, `identity|roles in the database match the catalog` (roles and permissions are code, the
  authorizer reads the database), plus the deployer's own checks: a doctor that crashed or printed no JSON, a doctor that
  ran as another environment than the deployer read from `.env`, ownership, and the HTTP checks.
* **GATED — stop the deploy, overridable:** `app|APP_URL uses https`, `storage|queue driver`, `secrets|secrets driver`,
  `tls|CA bundle for outbound TLS`, `catalog|the metering gap ratchet is not growing`,
  `storage|platform backup disk off the server`. Outside production:
  `ALLOW_DOCTOR_FAIL="<first 12 characters of the target SHA>:<reason, 10+ characters>"`, validated in preflight,
  logged with operator, both SHAs and the accepted rows. In production the variable is refused; a GATED row passes only
  through a line `Accept-Gate: <area|check> — <reason>` in the owner's signed tag.
* Every other row is printed (`REPORT …`) and stored in `report.json`, never gating. The liveness rows (scheduler,
  worker) cannot be judged while the units are stopped: run `onhost:doctor` again six minutes after the release.

### Exit codes

`0` released · `2` preflight refused · `3` drain, backup or verification failed (nothing switched) · `4` build failed
after the switch · `5` gate failed · `6` public `/up` failed after `up`. After 4 and 5 the site stays in maintenance
(re-issued without the bypass secret) and the drained units stay stopped; after 4, 5 and 6 the deployer prints the
recovery command from `last-good.json` (production: `REF=<tag>`), the backup set of the run and a pointer to this page.

## Rollback

* **Code:** run the printed command — the last good release through the same gate, backup not skipped:
  `REF=<last good sha or tag> EXPECTED_SHA=<sha> DEPLOY_OPERATOR=<name> /usr/local/sbin/onhost-deploy`. Migrations are
  backward compatible for one release, so no down migration is run. There is no automatic rollback: an operator decides.
* **Database, only when a migration corrupted data — with the owner's approval:**
  1. the units stay stopped (`systemctl stop onhost-scheduler.service 'onhost-queue@*'`), the site stays down;
  2. take a fresh backup of the current state first (`onhost:platform:backup && onhost:platform:backup:verify`);
  3. compare the checksum of the set you restore from with the one in `runs/<ts>/backup.out` of the release run (a copy
     outside the web tree: tamper evidence);
  4. `pg_restore --clean --if-exists -d onhost database.pgdump` from that pre-release set;
  5. reconcile the panels against the bindings before anything provisions again (`onhost:provisioning:reconcile`), and
     re-import payments received meanwhile (`onhost:bank:sync`). Whether a payment gateway retries a callback that got
     a 503 during the window is ASSUMED, not verified — check per gateway, and reconcile by hand after each deploy.
* **Data:** never roll back money or audit rows; correct with new documents/postings.
* If a release broke provisioning: freeze (`POST /v1/staff/provisioning/freeze`), roll back, thaw, retry failed
  operations.

## Configuration changes

Catalog, tax rules, SLA credit policies and notification templates are versioned rows, seeded from
`database/seeders` and changed by staff commands (`catalog.manage`, `billing.tax_rule.manage`, …), not by
editing production config. Feature flags live in `config/onhost.php` (`feature_flag.manage`).
