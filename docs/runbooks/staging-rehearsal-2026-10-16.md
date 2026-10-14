# Staging rehearsal, 2026-10-16: protocol (phase Q: A–C deploy of `36009513`, ST-6, read-only checks for P10 and P11)

Protocol of the run on staging (`staging.onhost.cz`, 45.67.217.22) on 2026-10-13 between 13:00 and 14:05 UTC, filed for the protocol
day 2026-10-16 (the days up to 2026-10-15 already have a protocol). It continues
[staging-rehearsal-2026-10-15.md](staging-rehearsal-2026-10-15.md) and follows the owner's document
`GENERALKA-Q-A-C-deploy-prikazy-2026-10-13.md` (desktop; the K procedure A–C with the frozen SHA of phase Q, plus ST-6).
**Outputs only, no secrets**: `app.env` was not read (ST-6 changed one line with `sed` and counted it with `grep -c`); no password,
TOTP, private key, cookie value or log content was printed. Backups and logs stay on the host in `/root/q-2026-10/` (root, 0700).

## Header

| Item | Value |
| --- | --- |
| Owner "ano" | 2026-10-13, "pokračuj fází Q, ano ke stagingu": Path B redeploy A–C of the phase Q tip after Q2–Q4 merged (backup, gate, rollback); ST-6 only after Q4 is deployed; read-only checks. Still the owner's: PP-1 (LE e-mail/terms/TXT), TOTP, secrets, permanent deletion (ST-1), production |
| Operator | Claude (root over `ssh -o BatchMode=yes onhost-staging`, key only), one step at a time, each verified before the next |
| Release | before `199071b862e872a0edf496b2f567de9128ac8207` → after **`36009513ca39f980f670af9da021030923e31e9a`** (merge PR #188; phase P #180/#181 and phase Q #184–#188) |
| Doctor | before 0 FAIL / 120 OK / 28 WARN · after C4 the same set · after ST-6 **0 FAIL / 121 OK / 27 WARN** |
| Migrations | 83 → **85** (`0001_01_01_001170_game_plans_without_unbacked_ddos`, `0001_01_01_001180_web_options_without_unbacked_addons`) |

## Step log (times UTC, 2026-10-13)

| Step | Time | Mode | Result | Notes |
| --- | --- | --- | --- | --- |
| Read-only state | 13:00 | S | OK | `VERSION` = `current` = `199071b8`; nothing parked; scheduler + queue default / mails / webhooks `active`; 264 GB free; vhost line 67 `add_header Strict-Transport-Security "max-age=31536000";` (input for P11); `expected-env` still `ONHOST_TOKEN_ORGANIZATION_REQUIRED=false` |
| P10 facts | 13:50 | S | OK | WAF attack log: 14 412 files, 69 MB, files **0666** `www:www`, directory 755, oldest 2026-07-07, 12 329 files older than 7 days, `config.json` `log_save: 30`, no logrotate entry; each file is a JSON array with one string (raw request). Only names, counts and modes were read → `docs/runbooks/aapanel-waf-log-privacy.md` (P10) |
| A backup | 13:36 | Z (A) | OK | `/root/q-2026-10` (0700): dump / state / tree `rc=0`; 5× `OK` in `sha256sum -c SHA256SUMS-q`; `TABLE DATA` 212; migrations 83; `expected-units` copied too |
| B `expected-nonok` | 13:38 | S | OK, **no write** | doctor 120 OK / 28 WARN; every WARN listed; `payments\|bank statement import` listed but OK (CLEARED, as on 2026-10-13) |
| C1 script + check | 13:40 | Z (C1) | OK | `staging.sh` from `36009513` = `6aa3ad41…` (the new spec line, Q4), `bash -n` OK, replaced `/root/onhost-staging.sh`; `deploy.sh` `9baa1a22…` and `deploy-gate.php` `653a7a2b…` unchanged; `check passed` |
| CI gate | 13:55 | — | OK | `tests`, `e2e smoke`, `security` green on `development` at `36009513` before C2 |
| C2 deployer | 13:58 | Z (C2) | OK | rc 0; `source-sha 36009513…`; `expected-env written`; `app.env matches expected-env`; `expected-env` line 30 now `ONHOST_TOKEN_ORGANIZATION_REQUIRED~=^(true\|false)?$` |
| C3 gated deploy | 14:00:47 – 14:01:36 | Z (C3) | OK | rc 0; run `runs/20261013-140049-36009513ca39`; `VERDICT pass`; no HARD-FAIL / GATED-FAIL / ROW-FAIL; `CLEARED payments\|bank statement import`; migrations 001170 (101 ms) and 001180 (52 ms) DONE; `released` / `current release` = `36009513` |
| C4 verification | 14:02 | S | OK | `VERSION` = `current` = `36009513`; nothing parked; 4 units `active`; `/up` 200 loopback and public; `nginx -t` OK; migrations 85; `product_options` with `priority_support`/`waf_cdn`: **0** (Q2); plan versions with `upstream L3/L4`: **0** (P2); doctor 120 OK / 28 WARN, same non-OK set; served home page without "Vyzkoušet zdarma" and `onhost-svc-other.js` without "NOC 24/7 v Praze" (Q1/Q3) |
| ST-6 precheck | 14:02 | S | OK | `operator:tokens:unbound --dry-run`: 0 unbound tokens, 0 used across organisations; 1 line `…=false` |
| ST-6 switch | 14:03 | Z (ST-6) | OK | copy `app.env` → `/root/q-2026-10/app.env.before-st6` (0600); `sed` `false` → `true` (1 line); `app.env` stays `640 root:www`; `config:cache`; `env-assert` rc 0 (the next deploy passes); row *API tokens must name an organisation (R9)* **OK**; `/up` 200; doctor 121 OK / 27 WARN |

## Findings

1. **ST-6 done; the R9 row is OK on staging.** The row stays listed in `expected-nonok`; the next deploy reports it as `CLEARED`
   (harmless). Removing it from the list is part of the R23 clean-up of the list.
2. **The app.env copy of ST-6** (`/root/q-2026-10/app.env.before-st6`, root 0600 in a 0700 directory) holds the staging secrets like
   the A backups do; it goes with the set when ST-2 (P-R61) deletes the old sets after the next successful cycle.
3. **HSTS still twice on staging** until the owner unticks HSTS on the vhost (P11 is merged after this deploy, PR #189; its doctor row
   will be WARN `2×` on the next release → add `security|HSTS is sent once and as configured` to `expected-nonok` in step B of the next
   cycle, or do the owner step first: `docs/runbooks/hsts.md`).
4. **The WAF attack log is world-readable (0666)** and older than its own 30-day setting — worse than finding 2 of 2026-10-14
   assumed; proposal and owner steps in `docs/runbooks/aapanel-waf-log-privacy.md` (P10, P-R73).

## Left for the owner (codes of the SEZNAM)

PP-1 (D6 certificate) and PP-2 … PP-6 (Penpot D7 → F7), ST-1 (`shred` of the two old dumps), ST-5 (VAT payer → non-payer, second
person), ST-7 (webhook ping to the owner's endpoint), ST-8 rest (guest order with an account, staff import with TOTP), WF-2 execution
(P10 steps), HS-1 vhost step (P11 runbook). ST-2: the sets `/root/o-2026-10`, `/root/j-2026-10`, `/root/r2b-2026-10` may now be
deleted (P-R61: after the next successful A–C — this one) — deletion is the owner's.

## Rollback

Not needed. Release rollback: Rollback B of the owner document (database from `/root/q-2026-10/onhost_staging_b-before-q.dump`,
then `deploy 199071b8…`). ST-6 rollback: the same `sed` back to `false`, `config:cache`.
