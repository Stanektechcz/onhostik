# Staging rehearsal, 2026-10-17: protocol (phase S, R3: A–C deploy of `fda0cedf`; D not run)

Protocol of the run on staging (`staging.onhost.cz`, 45.67.217.22) on 2026-10-13 between 21:23 and 22:35 UTC, filed for the protocol
day 2026-10-17 (the days up to 2026-10-16 already have a protocol). It continues
[staging-rehearsal-2026-10-16.md](staging-rehearsal-2026-10-16.md) and follows the owner's document
`GENERALKA-R-A-C-deploy-prikazy-2026-10-13.md` (desktop; the K/Q procedure A–C with the frozen SHA of phase R).
**Outputs only, no secrets**: `app.env` was not read; no password, TOTP, private key, cookie value or log content was printed.
Metric values are left out (only the names were checked). Backups and logs stay on the host in `/root/r-2026-10/` (root, 0700).

## Header

| Item | Value |
| --- | --- |
| Owner "ano" | 2026-10-13, "ano R3, pokračuj fází S": the A–C cycle of `fda0cedf` exactly per the owner document. **Step D (restore drill, the DB role's password typed by the owner) is not approved and was not run.** Still the owner's: D (DR-1), HS-2 (HSTS on the vhost), PP-1 … PP-7, secrets, TOTP, permanent deletion, production |
| Operator | Claude (root over `ssh -o BatchMode=yes onhost-staging`, key only), one step at a time, each verified before the next; the shell preparation of the document piped in with each step |
| Release | before `36009513ca39f980f670af9da021030923e31e9a` → after **`fda0cedf443a3c603ef2aeae1a6184fbaf7dfc6a`** (merge PR #203; phase Q #189–#196 and phase R #197–#203). Later commits on `development` up to `1c4853f5` (#204, #205) are documents and a docs test only — the frozen target stands |
| Doctor | before 0 FAIL / 121 OK / 27 WARN · after C4 **0 FAIL / 122 OK / 29 WARN** (3 new rows: HSTS WARN, restore drill WARN, scheduled-job budget OK) |
| Migrations | 85 → **86** (`0001_01_01_001190_scheduled_job_runs`, 34 ms) |

## Step log (times UTC, 2026-10-13)

| Step | Time | Mode | Result | Notes |
| --- | --- | --- | --- | --- |
| Read-only state | 21:23 | S | OK | `VERSION` = `current` = `36009513`; nothing parked; scheduler + queue default / mails / webhooks `active`; 264 GB free; `expected-nonok` 29 rows; vhost still has 1 `Strict-Transport-Security` line (HS-2 not done → B adds 2 rows); migrations 85; `nginx -t` OK; `/up` 200 |
| A backup | 21:23 | Z (A) | OK | `/root/r-2026-10` (0700): dump / state / tree `rc=0`; 5× `OK` in `sha256sum -c SHA256SUMS-r`; `TABLE DATA` 212; migrations 85 |
| B check | 21:23 | S | OK | doctor 121 OK / 27 WARN; `comm -13` (non-OK not listed) empty; none of the two rows already listed |
| B `expected-nonok` | 21:23 | Z (B) | OK | 2 rows appended under `# R-B 2026-10-13: … target fda0cedf…`: `security\|HSTS is sent once and as configured`, `storage\|platform restore drill within 35 days`; `-rw------- root root`; diff only 2× `>`; 31 rows |
| C1 script + check | 21:24 | Z (C1) | OK | `staging.sh` from `fda0cedf` = `6aa3ad41…` (identical to the installed one), `bash -n` OK, replaced; `deploy.sh` `9baa1a22…`, `deploy-gate.php` `653a7a2b…` as in the table; `check passed` |
| CI gate | 21:24 | — | OK | at `fda0cedf` (run of merge #203): `tests` (pest, pest-postgres 1–4, php 8.5, monitoring, restore-drill, deploy-scripts, openapi-lint), `e2e smoke`, `security` green |
| C2 deployer | 21:24 | Z (C2) | OK | rc 0; `expected-env written`; `app.env matches expected-env`; `source-sha` = `fda0cedf…` |
| C3 gated deploy | 21:24:56 – 21:25:31 | Z (C3) | OK | rc 0; run `runs/20261013-212457-fda0cedf443a`; `VERDICT pass`; no HARD-FAIL / GATED-FAIL / ROW-FAIL; `EXPECTED` 29 incl. the 2 new rows; `CLEARED payments\|bank statement import` and `CLEARED security\|API tokens must name an organisation (R9)` (harmless, R23 clean-up); migration 001190 DONE; `released` / `current release` = `fda0cedf` |
| C4 verification | 21:25 | S | OK | `VERSION` = `current` = `fda0cedf`; nothing parked; 4 units `active`; `nginx -t` OK; `/up` 200 loopback and public; migrations 86; PHP binary row OK (8.5.8, `/www/server/php/85/bin/php`); doctor 122 OK / 29 WARN, every non-OK row listed; `scheduled_job_runs` 7 rows; metrics `onhost_schedule_last_success_seconds`, `onhost_platform_restore_drill_timestamp`, `onhost_certificates_expired`, `onhost_disk_free_ratio` present with numbers; *every scheduled job ran within its budget* **OK** (92 jobs); *restore drill* **WARN** `no restore drill recorded yet`; *HSTS* **WARN** `2× (max-age=31536000 \| max-age=31536000)` |
| C4 again | 21:27 | S | OK | `scheduler running`, `queue worker alive` (default / mails / webhooks) OK; `scheduled_job_runs` 8 rows — **but `/metrics` still exports only `command="none"`** (finding 1) |
| Reads for S1/S2 | 22:05 | S | OK | `tld_policies`: 10 rows, all registrable (`com, cz, dev, eu, gg, io, net, org, pl, sk`) — input for S2 ("420+ koncovek") |
| Final read | 22:35 | S | OK | `fda0cedf`; 4 units `active`; `nginx -t` OK; public `/up` 200; doctor 122 OK / 29 WARN; `scheduled_job_runs` 49 rows (46 with a success); budget row OK |
| D restore drill | — | V | **not run** | not approved (DR-1); the owner types the DB role's password himself — document step D unchanged |

## Findings

1. **`/metrics` exported no scheduled job under PHP-FPM.** The schedule lives in `routes/console.php`, which only the console
   kernel loads; the web process saw an empty `Schedule`, so `onhost_schedule_last_success_seconds{command="none"}` was the only
   sample and the alert `OnhostScheduledJobOverdue` could never fire. The doctor (console) was right. Fixed in S3 (TASK-0212,
   PR #206): the scheduler leaves the budgets in the cache on its ticks and the web process reads them. Visible on staging in
   C4 of the next A–C cycle.
2. **HSTS twice** — unchanged until HS-2 (`GENERALKA-Q-V-HSTS-WAF-prikazy-2026-10-13.md`, step 1); the listed row turns `CLEARED`.
3. **Restore drill** WARN until step D (DR-1); the listed row turns `CLEARED` after it.
4. **`expected-nonok` now carries 4 rows that are OK** (`bank statement import`, R9 tokens, and later HSTS / restore drill) —
   harmless (`CLEARED`); dropping them is the R23 clean-up of the list.

## Left for the owner (codes of the SEZNAM)

DR-1 (step D, restore drill with the DB role's password), HS-2 (HSTS on the vhost), PP-1 … PP-7, ST-1 (`shred` of old dumps),
ST-2 (deleting the sets `/root/o-2026-10`, `/root/j-2026-10`, `/root/r2b-2026-10`, `/root/q-2026-10` after this successful
cycle, P-R61), ST-5, ST-7, ST-8 rest, WF-2. Production: nothing ran.

## Rollback

Not needed. Release rollback: Rollback C of the owner document (database from `/root/r-2026-10/onhost_staging_b-before-r.dump`,
then `deploy 36009513…`). `expected-nonok` rollback: `install -m 0600 -o root -g root /root/r-2026-10/expected-nonok.before-r …`.
