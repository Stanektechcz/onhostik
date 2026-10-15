# Staging rehearsal, 2026-10-19: protocol skeleton (phase W, A–C on the frozen target `cd65f8ec`)

Protocol of the phase W rehearsal on staging (`staging.onhost.cz`, 45.67.217.22), filed for the protocol day 2026-10-19 (the days up to
2026-10-18 already have a protocol). It continues [staging-rehearsal-2026-10-18.md](staging-rehearsal-2026-10-18.md) (attempt 2 of `82bc988f`
passed, V1 PASS). **This file is a skeleton: nothing has run.** Every result below reads `NEPROBĚHLO — čeká na T-ano` until the owner's
"ano" for this cycle names `cd65f8ec6c83b42079cab5c7968cc47c894bb2e4`, the SELL-1 write into `app.env` and the one `expected-nonok` row; the
operator then fills the tables step by step, each verified before the next.
The step commands are in the owner's document `GENERALKA-W-A-C-deploy-prikazy-2026-10-15.md` (desktop; it replaces `GENERALKA-V-…` for this cycle).
**Outputs only, no secrets**: `app.env` is not read or printed (step C1b prints counts, a checksum comparison and `true` only); no password,
TOTP, private key, cookie value or log content is printed. Metric values are left out (names and counts only).
Staging only; production, Wings, other sites, Proxmox, ISPConfig, Comgate and the bank are not touched.

## Header

| Item | Value |
| --- | --- |
| Owner "ano" | NEPROBĚHLO — čeká na T-ano (must name `cd65f8ec`, the SELL-1 write `ONHOST_SELL_DEFAULT=true` into `/etc/onhost/app.env` and the row `payments\|Comgate notification allow-list is filled`) |
| Operator | Claude over `ssh -o BatchMode=yes onhost-staging` (key only), one step at a time — NEPROBĚHLO |
| Release | before `82bc988f3de320bc828d1200da5385f2c9a8e71b` → after **`cd65f8ec6c83b42079cab5c7968cc47c894bb2e4`** (merge PR #234); NEPROBĚHLO |
| CI of the target | `tests` run 38653896467, `e2e smoke` 38653896490, `security` 38653896385 (2026-10-14 23:24 UTC) — re-check right before A |
| Doctor | before: 0 FAIL / 122 OK / 29 WARN (end of attempt 2) · after C4: expected 157 rows, 0 FAIL, 30–31 WARN · NEPROBĚHLO |
| Migrations | 88 → expected **90** · NEPROBĚHLO |
| `expected-nonok` | 32 rows → expected **33** · NEPROBĚHLO |

## What changed against `82bc988f` (verified from the repository)

- `git log --merges 82bc988f..cd65f8ec`: PRs #219, #220, #221 (SP1a mail reputation), #222 (W4 statement variable symbol), #223 (W1 Comgate
  notification allow-list), #225 (W3 sell switches), #226 (W6 payment sweep), #227, #228, #229 (docs), #230 (W2 doctor rows), #74
  (action-hook sliding window), #232 (W8 tests), #233 (guest checkout leaves no account), #235 (test time bomb), #234 (TASK-0235 daily
  settlement reconciliation). **Not in the target: #231 (security news, SN)** — step S-SN is skipped.
- **Migrations +2 (88 → 90)**, `git diff --name-status 82bc988f cd65f8ec -- database/migrations`: `0001_01_01_001220_mail_reputation_listings`
  (new tables `mail_reputation_checks`, `mail_reputation_listings`; `down()` drops them) and `0001_01_01_001230_reconciliation_run_per_provider_day`
  (column `reconciliation_runs.provider`, unique index `reconciliation_runs_provider_period_unique`; `down()` reverts).
- SHA-256 at `cd65f8ec`: `staging.sh` `5ac47f9b7d46dcd094c7096f90e9fe108a2d82327e8346ba9b6d862ee7c1ef34` (**changed**, was `6aa3ad41…`: `ONHOST_SELL_DEFAULT=true`
  in `envfill` and in the `expected-env` spec), `deploy.sh` `9baa1a2276d809cc306ff44dfe99a1ad5567cfe2a1497b93cdd4cd0c2f3d1496` and `deploy-gate.php`
  `653a7a2bbd6cd311b7725bdb4df79b328714b12e7c2cf534ffa70672845e1644` (both unchanged).
- **New environment requirement (SELL-1):** `ONHOST_SELL_DEFAULT=true` must be in `/etc/onhost/app.env` before step C2, because `staging.sh` pins it in
  `expected-env`; without it the deployer stops with `app.env does not match expected-env` and `deploy.sh` would exit 2. Staging's `app.env` is
  `root:www 0640`, so the key is written in place (same inode, mode and owner), not with `install -m 0600`.
- **New doctor rows (+6, 151 → 157):** `sales|product families are for sale` (OK with SELL-1), `payments|Comgate notification allow-list is filled`
  (**WARN on staging**, empty `COMGATE_CALLBACK_IPS`, the one new row for `expected-nonok`),
  `payments|every configured payment gateway was reconciled yesterday` (OK: no gateway configured), and the three SP1a rows
  `mail|sending addresses are on no major blocklist`, `mail|customer mail domains are on no major domain blocklist`,
  `mail|mail blocklist resolver is accepted` (all OK while staging has 0 nodes and no live mail domain — with the host resolver `8.8.8.8` the
  resolver row would be WARN as soon as the monitor has someone to ask).
- **New scheduled jobs (92 → 94 tracked):** `onhost:payments:sweep` (every ten minutes), `onhost:payments:reconcile` (05:15 Europe/Prague);
  `onhost:mail:blocklist` changes from daily 05:25 to hourly :25. Effect on `automation|every scheduled job ran within its budget` (already in
  `expected-nonok` since V-B): possibly WARN right after the deploy (never ran / shorter budget), OK after the first full hour; `reconcile` can hold
  it as "never ran" only when the deploy is after 2026-10-15 22:10 UTC and before the next 05:15 Prague run.
- **Step A takes a fresh backup** into a new set `/root/w-2026-10` (files `*-before-w*`, plus `app.env.before-w` copied `install -m 0600`);
  `/root/v-2026-10` is kept as it is. Reason as in attempt 2: Rollback C restores the database dump.

## Step log

| Step | Time | Mode | Expected | Result |
| --- | --- | --- | --- | --- |
| Read-only state | — | S | `VERSION` = `current` = `82bc988f`; nothing parked; 4 units `active`; migrations 88; `expected-nonok` 32 rows; `nginx -t` OK; `/up` 200 | NEPROBĚHLO — čeká na T-ano |
| CI gate | — | — | `tests` (pest, pest-postgres 1–4, php 8.5, monitoring, restore-drill, deploy-scripts, openapi-lint), `e2e smoke`, `security` green at `cd65f8ec` | NEPROBĚHLO — čeká na T-ano |
| A backup | — | Z (A) | `/root/w-2026-10` (0700): dump / state / tree `rc=0`; 6× `OK` in `sha256sum -c SHA256SUMS-w`; `app.env` `640 root:www`; migrations 88 | NEPROBĚHLO — čeká na T-ano |
| B `expected-nonok` | — | Z (B) | `config:show onhost.payments.default` = `comgate`; `COMGATE_CALLBACK_IPS` count 0; `comm -13` prints nothing; 1 row appended under `# W-B …`; `-rw------- root root`; diff only 1× `>`; 33 rows | NEPROBĚHLO — čeká na T-ano |
| C1 script + check | — | Z (C1) | `staging.sh` from `cd65f8ec` = `5ac47f9b…`; `deploy.sh` `9baa1a22…`, `deploy-gate.php` `653a7a2b…`; `check passed` | NEPROBĚHLO — čeká na T-ano |
| C1b SELL-1 write | — | Z (C1b) | `ONHOST_SELL_DEFAULT=true` in `app.env` once; other lines, mode, owner and inode unchanged; `parse-env` = `true`; `.env` still is `app.env` | NEPROBĚHLO — čeká na T-ano |
| C2 deployer | — | Z (C2) | rc 0; `app.env matches expected-env`; `expected-env` holds `ONHOST_SELL_DEFAULT=true`; `source-sha` = `cd65f8ec…` | NEPROBĚHLO — čeká na T-ano |
| C3 gated deploy | — | Z (C3) | rc 0; **`VERDICT pass`**; no HARD-FAIL / GATED-FAIL / ROW-FAIL; migrations `001220` and `001230` DONE; `EXPECTED` includes the Comgate allow-list row; `CLEARED` only rows already OK; `current release` = `cd65f8ec` | NEPROBĚHLO — čeká na T-ano |
| C4 verification | — | S | see *C4 expected values* | NEPROBĚHLO — čeká na T-ano |
| C4b after the next full hour | — | S | `sweep` and `blocklist` `failures_in_a_row` 0 with `last_success_at` set; budget row OK or held only by `reconcile` | NEPROBĚHLO — čeká na T-ano |
| C4c after 05:15 Europe/Prague | — | S | `onhost:payments:reconcile` ran without error; `reconcile` row OK; gateway row OK | NEPROBĚHLO — čeká na T-ano |
| V2 public site (read) | — | S | see *V2 expected values* | NEPROBĚHLO — čeká na T-ano |
| S-SN security news | — | — | skipped: #231 not in target | skipped: #231 not in target |
| D restore drill | — | V | not part of this cycle (DR-1, the owner types the DB role's password) | NEPROBĚHLO — čeká na DR-1 |
| Rollback | — | — | only on a failed step: `app.env` from `/root/w-2026-10/app.env.before-w` in place, `staging.sh` from `onhost-staging.sh.before-w`, DB from `onhost_staging_b-before-w.dump`, then deploy `82bc988f` | NEPROBĚHLO |

### C4 expected values

| Check | Expected | Result |
| --- | --- | --- |
| `VERSION` = `current` | `cd65f8ec6c83b42079cab5c7968cc47c894bb2e4`; 4 units `active`; `/up` 200 loopback and public | NEPROBĚHLO — čeká na T-ano |
| `SELECT count(*) FROM migrations` | **90** | NEPROBĚHLO — čeká na T-ano |
| `reconciliation_runs.provider` and index | column present (1), `reconciliation_runs_provider_period_unique` present (1) | NEPROBĚHLO — čeká na T-ano |
| `mail_reputation_checks` / `mail_reputation_listings` | both exist, 0 rows | NEPROBĚHLO — čeká na T-ano |
| 0 live nodes, 0 `mail_domain` bindings | 0 and 0 (otherwise the resolver row is conditionally WARN: STOP) | NEPROBĚHLO — čeká na T-ano |
| `config:show onhost-sell.default` | `true` | NEPROBĚHLO — čeká na T-ano |
| doctor | 157 rows, 0 FAIL; 127 OK / 30 WARN, or 126 OK / 31 WARN while the budget row is WARN; no non-OK row outside `expected-nonok` | NEPROBĚHLO — čeká na T-ano |
| `sales\|product families are for sale` | OK | NEPROBĚHLO — čeká na T-ano |
| `payments\|Comgate notification allow-list is filled` | WARN, "every notification is refused" (EXPECTED in the gate) | NEPROBĚHLO — čeká na T-ano |
| `payments\|every configured payment gateway was reconciled yesterday` | OK, "no payment gateway is configured" | NEPROBĚHLO — čeká na T-ano |
| `mail\|…` three SP1a rows | OK ("not checked yet" / "none listed" / "asked through the …") | NEPROBĚHLO — čeká na T-ano |
| `/metrics` (S3) | after ~90 s: `onhost_schedule_last_success_seconds` for `payments:sweep`, `payments:reconcile`, `mail:blocklist`; `command="none"` count **0**; `onhost_mail_blocklist_listings`, `onhost_mail_blocklist_refused`, `onhost_mail_reputation_last_check_timestamp` present | NEPROBĚHLO — čeká na T-ano |

### V2 expected values (public site, read only, mode S, loopback)

| Check | Expected | Result |
| --- | --- | --- |
| CDN page | `CDN a WAF · připravujeme` ≥ 1 in `onhost-svc-web.js` | NEPROBĚHLO — čeká na T-ano |
| Database without PITR | `PITR (7\|30\|90) dní` 0 in `onhost-svc-web.js` | NEPROBĚHLO — čeká na T-ano |
| Mail price list | `Antispam rspamd s pravidly serveru (fair use)` ≥ 1 in `onhost-data.js` | NEPROBĚHLO — čeká na T-ano |
| API changelog | `      changes: [],` once in `onhost-public.js` | NEPROBĚHLO — čeká na T-ano |
| Catalog with sales on | `GET /api/v1/catalog` answers 200 | NEPROBĚHLO — čeká na T-ano |
| Catalog revisions | `art onhost:catalog:revise` (dry run, **without `--apply`**): state only; both 2026-09 revisions still pending as in attempt 2 | NEPROBĚHLO — čeká na T-ano |

### Rollback

Release rollback: Rollback C of the owner document — `app.env` restored in place from `/root/w-2026-10/app.env.before-w`, `staging.sh` from
`/root/w-2026-10/onhost-staging.sh.before-w`, database from `/root/w-2026-10/onhost_staging_b-before-w.dump`, then deploy
`82bc988f3de320bc828d1200da5385f2c9a8e71b`. `expected-nonok` rollback: `install -m 0600 -o root -g root /root/w-2026-10/expected-nonok.before-w …`.

### Left for the owner (codes of the SEZNAM)

DR-1 (step D), HS-2 (HSTS on the vhost), CG-6 (Comgate notification addresses, clears the one new `expected-nonok` row), PP-1 … PP-7, ST-1, ST-2
(incl. `/root/r-2026-10`, `/root/t-2026-10` and `/root/v-2026-10` after a successful cycle), ST-5, ST-7, ST-8 rest, WF-2.
If this cycle passes, the production document `PRODUKCE-prikazy-…` moves to `cd65f8ec` (prepared diff on the desktop:
`PRODUKCE-prikazy-repoint-diff-2026-10-15.md`). Production: nothing ran.
