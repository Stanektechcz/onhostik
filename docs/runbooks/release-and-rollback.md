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
  one release after the code stopped using the column. The release check proves it mechanically, not by reading
  (`.claude/skills/ai-release-check/SKILL.md` step 4: a search of the range's migrations for drops, renames, `->change()`
  and columns added `NOT NULL` without a default); a rollback over a migration is rehearsed on staging with a target that
  really carries one (staging-launch.md S8 d).
* No release between 02:00 and 03:00 (the nightly platform backup at 02:15 would fall into the window and be skipped).

## Deploy (what `onhost-deploy` does, in this order)

```bash
REF=<tag|sha> EXPECTED_SHA=<40-hex sha> DEPLOY_OPERATOR=<name> PHP_FPM_RELOAD='<reload command>' /usr/local/sbin/onhost-deploy; echo rc=$?
```

| Stage | What happens | On failure |
| --- | --- | --- |
| preflight | lock (`flock`); `REF` and the full `EXPECTED_SHA` required, `BRANCH` refused; the deployer, `deploy-gate.php` and `source-sha` root-owned and writable by nobody else; APP_ENV and APP_URL read fail-closed from the root-owned `/etc/onhost/app.env` (`ENV_FILE`) — never from `$APP_DIR/.env`, which must be that very file (only `staging`, `local`, `testing` are not production); APP_URL host = `SITE`; `storage`, `bootstrap`, `bootstrap/cache` real directories (no symlinks); `.git` root-owned and not group/world-writable; `git fetch --tags`; REF resolved (production: an annotated tag whose `tag` header names it, whose last signature block is SSH with nothing but blank lines after it, and which `git verify-tag` accepts (the object id resolved once) against the root-owned `allowed_signers` — with `gpg.program`/`gpg.x509.program` set to `false` and an empty `GNUPGHOME`); the target must not carry a newer deployer than the installed one; tree clean (except the generated `contracts/openapi/onhost-v1.yaml` and `VERSION`); `SKIP_BACKUP`/`ALLOW_DOCTOR_FAIL` validated and bound to the target SHA; the root-owned `expected-units` exists and every unit it names is `enabled` and running (or was stopped by a previous failed run), and no other `onhost-queue@*`/`onhost-scheduler` unit runs; outside production also `expected-nonok` exists, `expected-env` holds (`deploy-gate.php env-assert` against `ENV_FILE`, values never printed; `PREFIX_*=` holds a whole env:// family empty) and no address of `egress-blocked` accepts a connection from the run user (a bare TCP connect per resolved address; review round 0) — each name resolving now and each address rejected by the host's `table inet onhost_containment`, an empty list only with the root-owned `path-b` marker (review round 1); `RUN_USER` exists, is not root, and `setpriv` with `setsid --wait` runs as it; `vendor/` is a real directory owned entirely by it (created and handed over when absent); the run user's work directory (`DEPLOY_WORK_DIR`, parent root-only) ready; `/up` reachable over loopback (401/403 = the vhost's basic auth is not bypassed for loopback) | exit **2**, nothing changed |
| drain | the active `onhost-queue@*` and `onhost-scheduler` units are stopped and waited for (`DRAIN_TIMEOUT`, default 300 s; a worker finishes its job first); the list is kept in `drained-units` | exit **3**, the units are started again |
| down | `artisan down --retry=60 --with-secret` (output discarded — the bypass URL never reaches a terminal or log). Every artisan and composer call runs as `RUN_USER` (`setpriv … -- setsid --wait env -i … </dev/null`, never as root — review round 0, security HIGH; in a session of its own and without the operator's terminal — review round 1) and reads Laravel's framework caches (`APP_PACKAGES_CACHE`, `APP_SERVICES_CACHE`, `APP_CONFIG_CACHE`, `APP_ROUTES_CACHE`, `APP_EVENTS_CACHE`) from the run's own directory in `DEPLOY_WORK_DIR`, not what the old release cached in `bootstrap/cache` | exit 3, units started |
| backup | `onhost:platform:backup`, the `Set …` line of THIS run, `onhost:platform:backup:verify <set>` must print `OK <set>`; the whole output (with the sha256 prefixes) stays in `runs/<ts>/backup.out` | exit **3**, nothing switched, the site back up if this run took it down |
| switch + build | `git checkout -f --detach <sha>` and a clean-tree check; bootstrap caches removed; `composer install --no-dev`; `migrate --force --isolated` with `lock_timeout=10s` (no retry); `AuthorizationSeeder`, `NotificationTemplateSeeder`; `config:cache`, `route:cache`, `event:cache` (into the run's directory, then copied into `bootstrap/cache` for PHP-FPM, by the run user); `onhost:openapi` by the run user (a failure is a WARN — on a root-owned checkout it cannot rewrite the tracked file and the committed contract stands; root never writes below `$APP_DIR`); `VERSION`; storage/bootstrap ownership repaired (`find -P … chown -h`, no symlink followed); `PHP_FPM_RELOAD` | exit **4** |
| gate | storage and bootstrap/cache owned by the PHP-FPM user; `.env` still the root-owned file; `onhost:doctor --json` judged by `deploy-gate.php` (below); `GET /up` and `GET /v1/status` must answer 200 through the maintenance bypass | exit **5** |
| live | the expected staging freeze re-asserted (`expect-freeze`), the drained units that are still in `expected-units` started (one taken off the list stays stopped) and, after `UNIT_SETTLE` (5 s), **every unit of `expected-units`** `active` — else all of them are stopped again and the site stays in maintenance (exit **7**); `artisan up` — only when the deployer took the site down (a site an operator took down stays down); public `GET /up` must answer 200 | exit **6** after `up` |
| record | `last-good.json` (sha, ref, tag, time, operator) and one line in `deploy.log` (from, to, stage, rc, backup set, drain seconds, window seconds, override, accepted rows, the first 12 of the sha256 of `expected-nonok`) | — |

State lives in `/var/lib/onhost-deploy/<site>/` (root, 0700): `deploy.log`, `last-good.json`, `drained-units`,
`down-by-deploy`, `expect-freeze`, `expected-units`, `expected-nonok`, `expected-env` and `egress-blocked` (staging), `allowed_signers`,
`gitconfig` and `runs/<ts>-<sha12>/` (`backup.out`, `report.json`, `verdict.out`, `env-assert.out`, `tag.txt` — the
signed message of the production tag, nothing after its signature).

The three lists (pre-mortem 2026-09-27, `docs/runbooks/staging-launch.md` O11/O12/S3) are root's decisions, not the
target's: `expected-units` — the units that must run after every release (`install.sh` writes it on a first install
from `QUEUES`; one unit per line, `#` comments, an empty file = none); `expected-nonok` — the doctor rows a staging runs
non-OK on purpose, each with its reason in the release record; `expected-env` — `KEY=value`, `KEY=` (empty or
absent), `KEY?` (set), `KEY!=value`, `KEY~=regex` and `PREFIX_*=` (every key of that family without a line of its
own empty or absent) lines the staging environment file must satisfy (a `<…>` placeholder is refused). A fourth,
`egress-blocked` (review round 0) — the `host:port` addresses the run user must not reach; a contained staging lists
every live endpoint of its kept database (`staging-launch.md` S0 GATE), an empty file = none.

The run user's own directory `DEPLOY_WORK_DIR` (default `/var/cache/onhost-deploy/<site>`, owned by `RUN_USER`, its
parent root's) holds its `HOME`, the composer cache and each run's framework caches (`run-<ts>-<sha12>/`, removed at
the end of the run). Root never runs the site's PHP (review round 0, security HIGH).

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
  through a line `Accept-Gate: <area|check> — <reason>` in the signed message of the owner's tag (a tag with anything after its signature is refused in preflight).
* **Every other row** (pre-mortem 2026-09-27 — before, they were printed and never gated, so a card gateway in test
  mode or a log mailer went live unnoticed although `Doctor.php` promises that a FAIL fails a production deploy):
  in **production** a `FAIL` row stops the release (`ROW-FAIL`, exit 5) unless the signed tag carries an `Accept-Gate:`
  line for it; a `WARN` row is non-blocking by the doctor's own definition and is printed. **Outside production** any
  non-OK row stops it unless `expected-nonok` names it (`EXPECTED …`); `ALLOW_DOCTOR_FAIL` does not open these rows. A
  listed row that is OK again is printed as `CLEARED`. `deploy-gate.php nonok --report <doctor json> --production 0|1`
  drafts the list (staging) or the Accept-Gate candidates (production).
* **Drained rows** — `automation|scheduler running`, `automation|queue worker alive`, `mail|outbox is leaving` — cannot
  be judged while the units are stopped: printed, never gating; the live stage requires every expected unit `active`,
  and `onhost:doctor` six minutes after the release must show them OK (staging-launch.md S7/D).

### Exit codes

`0` released · `2` preflight refused · `3` drain, backup or verification failed (nothing switched) · `4` build failed
after the switch · `5` gate failed · `6` public `/up` failed after `up` · `7` a drained unit did not come back. After 4,
5 and 7 the site stays in maintenance (re-issued without the bypass secret) and the drained units stay stopped; after
4 to 7 the deployer prints the
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
