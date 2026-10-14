# Staging rehearsal, 2026-10-12: protocol (deploy `fa2ea75d`, `--legal`, Penpot, R3–R25, G2)

Protocol of the run on staging (`staging.onhost.cz`, 45.67.217.22) on 2026-10-12. It continues
[staging-rehearsal-2026-10-11.md](staging-rehearsal-2026-10-11.md) (opened, no step run) and follows the owner's documents on the desktop:
`GENERALKA-K-A-C-deploy-prikazy-2026-10-12.md` (A–C), `GENERALKA-Penpot-D-G-prikazy.md` (D–G), then
[staging-rehearsal-2026-10.md](staging-rehearsal-2026-10.md) R3–R25, then G2. **Outputs only, no secrets**: no `app.env` was read, no
password, TOTP, key or TXT value was typed or printed. Full reports stay on the host in `/root/j-2026-10/` and
`/root/rehearsal-2026-10/` (root, 0700).

## Header

| Item | Value |
| --- | --- |
| Owner "ano" | 2026-10-12, "Udávám ano ke krokům": A–C, then `onhost:production:prepare --legal`, then Penpot D–G, then R3–R25, then G2; decision 10 by default (after success: drop `onhost_staging_b_r2b5_failed` and old dumps; Penpot test instance removed after E2E) |
| Operator | Claude (root over `ssh onhost-staging`, key only), one step at a time, each verified before the next |
| Release before | `e711b7c7` (VERSION = `releases/current`), deployer `b3ac309d`, 65 migrations |
| Release after | `fa2ea75d6a4f276cc2a248c7829e76f1735e65ed` (VERSION = `releases/current`), deployer `fa2ea75d`, 82 migrations |
| Doctor | after C4: 0 FAIL, 31 WARN · R1 (after `--legal`): 0 FAIL, 30 WARN · R23: **0 FAIL, 29 WARN** · R24 + 6 min: 0 FAIL, 29 WARN, no row moved |
| `expected-nonok` after R23 | sha256 prefix `7621ce8fb60f` (copy before R23: `$S/expected-nonok.bak`) |

## Step log (times UTC)

| Step | Time | Result | Gate / doctor | Notes |
| --- | --- | --- | --- | --- |
| A backup | 08:54–08:55 | OK | migrations 65 | dump / state / tree `rc=0`; 5× `sha256sum -c` OK; 206 `TABLE DATA`; 265 GB free; VERSION = current = `e711b7c7` |
| B `expected-nonok` (+6 WARN) | 08:55 | OK | — | none of the 6 rows already listed; identical to the R2b-5 proposal; after the write `-rw------- root root`, diff only 6× `>` |
| C1 `staging.sh` + `check` | 08:56 | OK | — | three sums equal the table of section 0 (`cc3827f4…`, `9baa1a22…`, `653a7a2b…`); `bash -n` clean; `✔ vhost PHP-FPM matches the deploy PHP`, `✔ check passed`, no ✖ |
| C2 deployer | 08:56–08:57 | OK | — | `rc=0`; `✔ app.env matches expected-env`; `source-sha` = `fa2ea75d…` |
| C3 gated deploy | 08:57–09:00 | OK | `VERDICT pass`; 31 EXPECTED, **5 CLEARED**, 0 ROW-FAIL, 0 GATED-FAIL, 0 HARD-FAIL | `rc=0`; `released: fa2ea75d…`; 000949 and 000950 ran (`DONE`); `/up` 200, `/v1/status` 200. Prediction was 4 CLEARED: the fifth is *the national bank's exchange rates are fresh* (OK now) — benign |
| C4 verification (R2b-6) | 09:00–09:01 | OK, one doc deviation | 0 FAIL, 31 WARN | VERSION = current = `fa2ea75d`; 3 units active; `/up` loopback 200, public 200; migrations **82**; PHP binary row OK (`/www/server/php/85/bin/php`); the 6 commands listed; after 6 min `scheduler running` and `queue worker alive` OK. **Deviation:** `status` still prints `PARKED: sha=b3ac309d… (2026-10-08)` — see *Findings* 1 |
| `--legal` (R14, G-2) | 09:01 | OK, owner step left | 0 FAIL, 30 WARN | `rc=0`; *Legal entity written from the environment*; *legal entity identification* OK; *legal entity bank details real* WARN → **OK**. `onhost:vat:payer-mode` **rc 1**: the existing entity is a payer while `ONHOST_VAT_PAYER=false` — the switch is a person's CRITICAL action (finance step-up + second person), never the command line → owner |
| D0 Penpot checks | 09:01 | **STOP** | — | everything as expected (release has `e89d7fa9`, `docker_host` ×5, Ubuntu 22.04 / 5.15 / kvm, Docker 29.6.1, ports 8081/8444/2019/19xxx free, 8080 = Wings, cgroup2, sshd 666 on 0.0.0.0 + `[::]`, no `onhost` user, no leftovers, 14 rootful containers, ufw active, 265 GB free, ~6.9 GB available RAM, nginx 1.31.2, DNS → 45.67.217.22, no AAAA, region `cz1`) **except** `provider_instances`: `wedos-main|wedos|active` (expected empty) — STOP of D0. Rollback D0 run (`rm -rf /root/penpot-stg`). See *Findings* 2 |
| D1–D8, E1–E6, F0–F8 | — | not run | — | branch stopped at D0 (and D6, F1, F4–F7 need the owner anyway: DNS-01 TXT in the live zone + his e-mail and ToS, staff console with step-up, customer panel login) |
| G1 Penpot clean-up | — | not needed | — | nothing was created (no user, node, instance, secret, certificate, vhost) |
| R1 baseline (precondition of R3) | 09:02 | OK | 0 FAIL, 30 WARN | read only; watched rows below |
| R3 usranalyse directives | 09:02:40–09:02:52 | OK | 6 min later: *scheduler running*, *queue worker alive* (+ default, mails) OK | module preloaded; `harden` rc 0, no ✖; units active after 10 s; no crash; three drop-ins present; `ProtectSystem=no`, `NoNewPrivileges=yes`, `PrivateTmp=yes`, `ProtectKernelTunables/Modules/ControlGroups=yes`, `RestrictSUIDSGID=yes`, `LockPersonality=yes`; modes `root:root 755 public`, `www:www 755 public/build`, `root:www 750 /etc/onhost`, `root:www 640 app.env`; omit list empty (every directive held) |
| R4 TRUSTED_PROXIES | 09:02 | OK, no change | *trusted proxies are exact addresses* OK | already loopback only (H-R2); nothing written |
| R5 shared cache | 09:02 | OK, no change | *cache store is shared (rate limits)* OK | store `redis`; nothing written |
| R6 web plans, dry run | 09:03 | OK | — | 3 e-shop plans lose `mailboxes`, 4 `dedicated_ipv4` options withdrawn, 0 services keep v1; no-id run lists 4 pending revisions, no `custom-iso` proposal |
| R7 web plans, apply | 09:03 | OK, one row stays WARN | *revision 2026-10-deliverable-web-plans applied* **OK**; *every catalogue revision is applied* WARN | published `eshop/shop-growth|shop-peak|shop-start` v1 → v2, 4 options withdrawn. The second row stays WARN only for `2026-09-honest-promises`, `2026-09-shared-php-workers` (not part of R3–R25) and `2026-10-penpot-on-sale` (until R20) — already on `expected-nonok` |
| R8 custom ISO, dry run | 09:03 | OK | — | `vps/compute-4/8/16`, `vds/vds-4/8/16`: `custom_iso null → true; custom_iso_max_mb null → 4096` (= proposal; not `compute-2`); `not ready (I-R9)`: R15 no clamd, R17 no usable Proxmox instance |
| R9 Penpot, dry run | 09:03 | OK | — | create product `penpot` (`penpot-team` 29 Kč / 1.19 EUR a month, active) and the rule per web tariff (9 tariffs, all included) |
| R10 staff TOTP | 09:02 | OK, no change | *staff and demo accounts have an authenticator* OK | 2 staff accounts, both confirmed; enrolment is the owner's own step and was not needed |
| R11 exchange rates | 09:04 | OK | *exchange rates are fresh* OK | list of 2026-10-09, 0 new rates, 0 documents waiting |
| R12 tokens | 09:04 | dry run OK; switch **not run** | *API tokens must name an organisation (R9)* WARN (off) | 0 unbound, 0 cross-organisation, 0 past the cap (3 dry runs). The switch is the owner's edit of `/etc/onhost/app.env` → owner |
| R13 reinstatement audit | 09:04 | OK | *pay and restore (services.reinstate)* OK | four lists, all 0; nothing changed |
| R14 VAT entity | 09:01 | partly | *VAT payer mode agrees with the legal entity* WARN | see `--legal` above; the payer → non-payer switch of the existing entity is the owner's console step |
| R15 clamd | — | skipped | — | needs the owner's `app.env` values and a clamd; *virus scanner (clamd)* WARN as before |
| R16 ISO volume, PHP, nginx | — | skipped | — | needs a dedicated volume and the owner's `app.env` / PHP / nginx edits |
| R17 Proxmox ISO storage | — | skipped (known limit) | — | no Proxmox instance on staging (decision 3) |
| R18 custom ISO, apply | — | skipped | — | precondition R15–R17 green not met; `CustomIsoReadiness` would refuse |
| R19 ISO upload smoke | — | skipped | — | needs R18 and a VPS |
| R20 Penpot, apply | 09:05 | OK | *Penpot has a price before it is on sale* OK; *Penpot is sold only with a Penpot node to run it* **WARN** (runbook predicted a non-blocking FAIL) | product `penpot` created, state `active`, `pricing.penpot` written; detail: the cart refuses every Penpot order (`penpot_unavailable`), nothing charged. Rollback if wanted: `art onhost:catalog:state draft penpot` |
| R21 webhook lane | — | **skipped (doc defect)** | *rotated webhook secrets overlap only briefly* OK | the runbook line `echo 'onhost-queue@webhooks' >> $S/expected-units` would make every later deploy die rc 2 (see *Findings* 3); fixed in the runbook by this task; the test ping needs the owner's endpoint and API credentials |
| R22 dead letters | 09:05 | OK (doctor part) | *no outbox dead letters* OK | 0 dead letters; no Prometheus / `promtool` on staging, so the rule check is not done here |
| R23 final doctor, `expected-nonok` | 09:09 | OK | **0 FAIL, 29 WARN**; verdict before the edit `row` (1 ROW-FAIL, 8 CLEARED), after `VERDICT pass` | the 8 CLEARED rows dropped, `penpot|Penpot is sold only with a Penpot node to run it` added with its reason (comment line in the file); `onhost:staging:report --check` rc 0 (1 panel instance, 0 slow actions) |
| R24 gated deploy | 09:10:06–09:10:46 | OK | `VERDICT pass`, no ROW-FAIL / CLEARED; +6 min: 0 FAIL, 29 WARN, no row moved; drained rows OK | `rc=0`; `/up` 200, `/v1/status` 200; VERSION = current = `fa2ea75d`; 3 units active |
| R25 compute backups | 09:11 | read-only part OK, rest skipped | *servers and databases sold backups get them* OK; *every Proxmox instance carrying sold backups has a backup_storage* OK | `compute-plan`: 0 services, no `MISSING`, rule `backups.compute` off; no Proxmox / PBS on staging (decision 4), so no test backup; the rule stays off |
| G2 failed DB copies | 09:11 | OK | `/up` 200 | `onhost_staging_b_r2b5_failed` (68 MB): forensic dump `/root/j-2026-10/onhost_staging_b_r2b5_failed.dump` (211 `TABLE DATA`), then dropped; `onhost_staging_b_r2b5a_failed` did not exist; only `onhost_staging_b` remains |

## R1 → R23, the watched rows

| Doctor row | R1 | R23 |
| --- | --- | --- |
| PHP binary is the one the deploy and the workers must use | OK | OK |
| trusted proxies are exact addresses | OK | OK |
| cache store is shared (rate limits) | OK | OK |
| every catalogue revision is applied | WARN | WARN (honest-promises, shared-php-workers pending) |
| catalogue revision 2026-10-deliverable-web-plans applied | WARN | **OK** |
| staff and demo accounts have an authenticator | OK | OK |
| exchange rates are fresh | OK | OK |
| API tokens must name an organisation (R9) | WARN | WARN (switch is the owner's) |
| VAT payer mode agrees with the legal entity | WARN | WARN (entity switch is the owner's) |
| custom ISO virus scan passes its self-test | OK (nothing sold) | OK (nothing sold) |
| custom ISO storage has room | OK (nothing sold) | OK (nothing sold) |
| rotated webhook secrets overlap only briefly | OK | OK |
| no outbox dead letters | OK | OK |

Moved between C4 and R23: *legal entity bank details real* WARN → OK (`--legal`), *every plan on sale is one its own server can deliver*
WARN → OK and *revision 2026-10-deliverable-web-plans applied* WARN → OK (R7), *Penpot is sold only with a Penpot node to run it*
OK → WARN (R20, listed with its reason).

## Findings

1. **C4 prediction wrong, not a failure.** The owner document expected `nothing parked` (`b3ac309d….parked` renamed `.cleared`).
   `staging.sh` `release_good()` only clears the park of the SHA being released, so the 2026-10-08 park of `b3ac309d` stays and
   `status` keeps printing it. The current release is good (rc 0, `VERDICT pass`, current = VERSION). The file was **not** touched
   (no documented step); renaming it is the owner's choice.
2. **Penpot D0 STOP.** `provider_instances` holds `wedos-main` (registrar WEDOS, created 2026-09-28, no credentials: doctor
   *missing credentials: login, wapi_password*). Both D0 and F0 require an empty table besides `penpot-stg1` (thawing provisioning
   in F3 is global). The 2026-10-07 protocol also said "no provider instance at all" — both documents missed it. Next: the owner
   decides whether `wedos-main` may stay during a thaw (then the documents get the exception) or is disabled first; D0 is then
   repeated. Penpot E2E: **not run**.
3. **R21 runbook defect.** `deploy.sh` accepts only `onhost-scheduler.service` and `onhost-queue@<lane>.service` in
   `expected-units` (`die 2 … only onhost-scheduler.service and onhost-queue@<lane>.service`); the runbook appended
   `onhost-queue@webhooks` without `.service`. The step was skipped instead of improvised; the runbook now names
   `onhost-queue@webhooks.service`.
4. **R20 severity.** The node row is WARN, not the non-blocking FAIL the runbook expected; it is on `expected-nonok` with its reason.

## Left for the owner

- VAT: switch the legal entity to non-payer in the staff console (finance step-up + second person or the sole approver's time lock).
- R12 switch `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true` (0 tokens affected), R15/R16 (`app.env`, clamd, ISO volume), R21 again with
  the fixed line and a test ping, R25 test backup once a Proxmox + PBS exists.
- Penpot: decision on `wedos-main` (finding 2), then D0–G1 again; D6 (DNS-01 TXT, e-mail, Let's Encrypt terms), F1 sandbox switch,
  F4–F7 panel steps are his.
- Old dumps (decision 10, no documented command, nothing deleted): `/root/r2b-2026-10/onhost_staging_b-before-r2b.dump` and
  `…-before-r2b5.dump` (about 4.4 MB each); `/root/j-2026-10` keeps the A backups and the G2 forensic dump.
- The stale `releases/b3ac309d….parked` (finding 1).
