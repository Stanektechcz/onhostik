# Production launch plan (phase J, 2026-10-11; refreshed in phase R, 2026-10-13)

**Prepared, not run. Nothing in this document has been executed on a production host, not even a read over SSH.** Every step
below is the owner's action or needs the owner's explicit "ano" for that step (PROMPT-faze-J, *Pověření*): a deploy, a migration,
`--legal`, TOTP enrolment, a catalogue revision, a switch. The order comes from [go-live-checklist.md](go-live-checklist.md)
(sections 0 and 10, blockers B1–B13) and is meant to be rehearsed on staging first ([staging-rehearsal-2026-10.md](staging-rehearsal-2026-10.md),
protocol of phase J: `staging-rehearsal-2026-10-11.md`). The first day after the release is [first-day-production.md](first-day-production.md).
The owner's command sheet built from this plan is `PRODUKCE-prikazy-2026-10-11.md` on the owner's desktop (marked *NESPOUŠTĚT bez
souhlasu*).

What this plan does **not** decide (owner decisions 2–10 of `ROZHODNUTI-podklady-J-2026-10-11.md`): the date, the host (PHP 8.5 like
staging?), the maintenance window, the second person for four eyes or `ONHOST_FOUR_EYES=false`, DNS and TLS of the production domain,
which environment Comgate reviews. Each step that depends on one names it.

## 0. Phase R refresh (2026-10-13) — read this first

**Target:** the frozen SHA of phase R, `<R-SHA>` (development after PR #203; docs-only commits after it do not change what is
deployed). Staging runs `36009513` (phase Q) until the owner's next "ano" to an A–C cycle of `<R-SHA>`
(`GENERALKA-R-A-C-deploy-prikazy-2026-10-13.md` on the owner's desktop); **P1 below is therefore not met for `<R-SHA>`** and no
step of this plan may run in production before it is.

What phase P–R added to the order (each row below is marked *P…*/*R…*; the owner's sheet is `PRODUKCE-prikazy-2026-10-13.md`,
which replaces the 2026-10-11 skeleton):

| Added | Where in §2 | Why |
| --- | --- | --- |
| Restore drill of the platform backup (P13) | step 2a | a backup that was never restored is not one; the doctor row *platform restore drill within 35 days* warns until it ran |
| HSTS from the application only (P11, P-R74) | step 3a | the vhost of aaPanel must not send a second header; doctor *HSTS is sent once and as configured* |
| Token switch rehearsed on staging (Q4, ST-6, R12) | step 8 | `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true` passed the deployer spec and the doctor on staging (protocol 2026-10-16) |
| Referral cookie stays off (P20, P-R75) | step 9a | `ONHOST_PARTNERS_REFERRAL_COOKIE=false` until the lawyer answers; the code from `?ref=` travels in the sign-up |
| Monitoring VM (P12, P-R19 = PR-13, OP-4) | step 10 | the rules are unit-tested in CI (`monitoring` job); the VM, its address and the e-mail route are the owner's |
| WAF attack log: 7 days, no cookies, root 0600 (P10, P-R73) | step 10a | the btwaf log kept request headers readable by every user |
| The staff page *Provoz* (P16) and the accountant's CSV (R2) | first day / day 2 | read-only; nothing to switch |
| Go/no-go, T-48 h TTL, DNS cutover, rollback commands | §8 | the cutover itself |

Owner inputs this plan still waits for (SEZNAM codes): PR-1 (date), PR-2 (host), PR-4 (window), PR-5 (second person), PR-6 (staff
e-mails), PR-7 (target IP), PR-8 (TLS e-mail), PR-9 (signing key), PR-10 (backup bucket), PR-11 (SMTP), PR-12 (Turnstile), PR-13
(monitoring VM), PR-15 (products on sale on day one), PR-17 (WAPI allow-list), CG-1 … CG-6 (Comgate). Decided defaults: P-R2 … P-R23.

## 1. Preconditions (none of them is met on 2026-10-11; on 2026-10-13 still none — P1 needs an A–C cycle of `<R-SHA>`)

| # | Precondition | Why | State 2026-10-11 |
| --- | --- | --- | --- |
| P1 | Staging runs the frozen target SHA through the gate (sections A–C of the owner's rehearsal document) and R3–R24 are recorded | B1: no step of the checklist is proved on a real host until then | staging still on `e711b7c7`; A–C wait for the owner (`GENERALKA-J-A-C-deploy-prikazy-2026-10-11.md`) |
| P2 | The production host exists and passed `infra/aapanel/install.sh` + the doctor's environment rows | the gated deployer and its state directory | not decided (decision 9) |
| P3 | Owner decisions 2, 3, 4, 8, 9 answered | they choose steps below (Comgate variant, custom ISO, compute backups, Penpot, date/host) | open |
| P4 | Lawyer's answer (decision 6) or the accepted risk I-R4 B stays | which legal version is live on the first day | I-R4 B: launch on `2026-09`; `2026-10` is an unpublished draft |
| P5 | `onhost-deploy` installed from the same commit as the release; `allowed_signers` holds the owner's key | production deploys only a signed tag (release-and-rollback.md) | not installed |

## 2. The order on the production host

One doctor run after every step (`php artisan onhost:doctor`; each row that is not OK names its remedy). A step that fails is not
retried another way without a new "ano"; its cause is written down and the fix becomes a package.

| # | Step | Command (on the production host, as the documented user) | Expected | Rollback | Checklist |
| --- | --- | --- | --- | --- | --- |
| 1 | **B6 reinstatement audit** on the release that runs before the deploy (on a fresh install with no data it lists nothing — run it anyway and keep the output) | `php artisan onhost:billing:reinstatement-audit` (read only) | a list (or none) kept with the release record | none (read only) | step 0, B6 |
| 2 | **Backup** of the database and of the deployer state, verified | `php artisan onhost:platform:backup && php artisan onhost:platform:backup:verify` (the deployer takes its own set too) | a verified set named in the release record | — | release-and-rollback.md |
| 2a | *P13:* **Restore drill** of the newest platform backup on the host (a throw-away database, dropped again; the application's own DB role, its password typed) | `bash infra/aapanel/restore-drill.sh` as root with `PGHOST`/`PGUSER` (backups.md *Restore drill of the platform backup*) | exit 0; doctor *platform restore drill within 35 days* OK | nothing to undo (the drill drops its database; exit 1 = an incident: the backup does not restore) | go-live §1 |
| 3 | **Deploy the owner's signed tag through the gate** | `REF=<tag> EXPECTED_SHA=<40-hex> DEPLOY_OPERATOR=<name> PHP_FPM_RELOAD='<reload>' /usr/local/sbin/onhost-deploy; echo rc=$?` | `rc=0`; doctor 0 FAIL except rows the tag's `Accept-Gate:` names (none expected); migrations `000949` + `000950` pass (B7: `000949` prints any newer row of a double commission it set aside — finance cancels each after the release through the bus; it stops only on a newer row in an open payout) | `REF=<last good> EXPECTED_SHA=<sha> … onhost-deploy` (the deployer refuses a downgrade of itself, not of the app) | step 17 gate, B7 |
| 3a | *P11:* **HSTS from the application only** (P-R74): the vhost must not send its own | aaPanel → Website → <site> → SSL → untick *HSTS* → Save; `grep -n 'Strict-Transport-Security' /www/server/panel/vhost/nginx/<site>.conf`; `nginx -t` ([hsts.md](hsts.md)) | `curl -sI https://<site>/up` shows the header once; doctor *HSTS is sent once and as configured* OK | tick *HSTS* again | P-R74 |
| 4 | **Legal entity into the database** (B2, G-2): the config defaults are the real data (I-R11, I-R15) | `php artisan onhost:production:prepare --legal` | doctor *legal entity …* rows OK; `onhost:vat:payer-mode` exits 0 (not a payer, H-R0) | the entity is data: correct with the same command and the right values before the first document; after it, only new documents change | step 11, G-1…G-3 |
| 5 | **Staff authenticators** (B3): the owner enrols every staff account himself and types every code | `php artisan onhost:staff:totp <email>` then `… --code=<6 digits>` per account | *staff and demo accounts have an authenticator* OK (0 missing) | `--reset` for a lost device | step 6 |
| 6 | **Catalogue revisions** (dry run, then apply; four eyes unless `ONHOST_FOUR_EYES=false`) | `php artisan onhost:catalog:revise`, then `--apply` per revision; **not** `2026-10-custom-iso` (I-R9: only after R15–R17; decision 3 B = launch without it) | *every catalogue revision is applied* OK except custom ISO (deliberately) | a new plan version, never an edit | steps 4, 13 |
| 7 | **Exchange rates** | `php artisan onhost:fx:sync` | *exchange rates are fresh* OK | none (data) | step 5 |
| 8 | **API tokens name an organisation** (R9): look, tell holders, then switch — *rehearsed on staging (ST-6, protocol 2026-10-16; Q4 made the deployer spec accept `true`)* | `php artisan operator:tokens:unbound --dry-run`, then `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true` + `config:cache` | *API tokens must name an organisation* OK | the switch back + `config:cache` | step 10 |
| 8a | *P20:* **Referral cookie stays off** until the lawyer answers (P-R75) | `ONHOST_PARTNERS_REFERRAL_COOKIE=false` in `app.env` (the default; check it is not set to true) | the privacy page lists no `onhost_ref`; a `?ref=` link still credits the partner in the sign-up | — | P-R75 |
| 9 | **Webhook lane and secret overlap** | `systemctl enable --now onhost-queue@webhooks`; `ONHOST_WEBHOOK_SECRET_OVERLAP_MINUTES` (default 60) | *rotated webhook secrets overlap only briefly* OK | stop the unit (default lane takes over) | step 15, G-11, G-12 |
| 10 | **Dead letters are heard** (B12) — *P12: on the monitoring VM of [monitoring-setup.md](monitoring-setup.md) (P-R19, PR-13/OP-4); the rules are unit-tested in CI* | `promtool check rules infra/monitoring/slo-alerts.yml`, load into Prometheus, Alertmanager routes for `ticket` and `page` | `onhost_outbox_dead_letters` returned by a query; *no outbox dead letters* OK | remove the rule file | step 16, G-13 |
| 10a | *P10:* **WAF attack log**: 7 days, request headers not kept, root 0600 (P-R73) | the owner's steps of [aapanel-waf-log-privacy.md](aapanel-waf-log-privacy.md) (`/etc/cron.d/onhost-btwaf-log`) | counts before/after, `stat` 0600 root, a test block still logged | `rm /etc/cron.d/onhost-btwaf-log` | P-R73 |
| 11 | **The rest of section 0**: PHP binary, shared cache store, capacity basis, Turnstile keys (B8, keys not procured yet), API base URL | checklist steps 1, 3, 7, 8, 9 | their rows OK, or WARN with the owner's decision | per row | steps 1, 3, 7, 8, 9 |
| 12 | **Final doctor** | `php artisan onhost:doctor --json` into the release record | 0 FAIL; every WARN has a remedy and an owner decision | — | step 17 |
| 13 | **First day** | [first-day-production.md](first-day-production.md) from H0 | as written there | its rollback triggers | — |

Steps 4–11 are the operator's on a running release; they can run in the same maintenance window as step 3 or the day after, but
**step 4 must precede the first order** (a document freezes its seller) and step 5 must precede any staff login in production.

## 3. Blockers B1–B13 (state 2026-10-11)

| # | Item | Kind | State 2026-10-11 | Cleared by |
| --- | --- | --- | --- | --- |
| B1 | Staging rehearsal R0–R24 | blocking | not run: staging on `e711b7c7`, the last gated deploy (`b3ac309d`) stopped at rc 5 and was rolled back (protocol 2026-10-07). Phase J: A–C first, then Penpot D–G, then R3–R24, R25 — each step with the owner's yes | protocol `staging-rehearsal-2026-10-11.md` complete |
| B2 | Legal entity in the database | blocking | config defaults are the real data (I-R11, I-R15); not written into the staging or production database | step 4 (staging first: rehearsal step after C) |
| B3 | Staff authenticators | blocking | not enrolled in production | step 5 (owner) |
| B4 | Lawyer's answer (H-R5, `LEGAL_REVIEW_2026-10.md`) | accepted risk (I-R4) | waiting for the lawyer (decision 6) | section 6 below |
| B5 | Comgate never answered a real request | blocking for card sales | the site must pass Comgate's manual review first (I-R10): packages J4 (site), J7 (browser check), decisions 2 and 7 | section 5 below, then package J11 |
| B6 | Reinstatement audit before the H0 deploy | blocking (order) | not run | step 1 |
| B7 | `000950` stops on a genuine double commission | resolved in code | K5 (TASK-0158, J-R3): `000949` sets the newer row aside before `000950`; `partner.commission.cancel_duplicate` (HIGH, step-up, four eyes) | after step 3: finance cancels every row `000949` printed (the staff partner detail lists them); an open payout holding a newer row stops the deploy — finish or reject it first |
| B8 | Turnstile keys | should | keys not procured | step 11 |
| B9 | clamd for uploads | should (blocking for custom ISO) | not built | G-4; not needed while custom ISO is not sold (decision 3 B) |
| B10 | `backups.compute` off | should | decision 4 open (recommended B: after the launch; `backup_days` stays in `KNOWN_GAPS`) | decision 4 |
| B11 | Penpot without a node | should | staging E2E (D–G) not run; no production node; orders are refused honestly and refunded | decision 8 after the staging E2E |
| B12 | Dead-letter alerts | should | no Prometheus loaded | step 10 |
| B13 | Cache store, PHP binary, revisions, rates, token switch | should | WARN until the steps run | steps 6, 7, 8, 11 |

## 4. Phase G steps G-1 … G-15 (state 2026-10-11)

| Step | State 2026-10-11 |
| --- | --- |
| G-1 | decided: not a VAT payer now (H-R0), at least a year (I-R5, I-R8) |
| G-2 | = B2, step 4 |
| G-3 | not applicable while a non-payer; a later switch is finance + step-up + second person |
| G-4 … G-7 | custom ISO infrastructure not built; staging has no Proxmox (R17 cannot be green) — decision 3 (recommended B: launch without custom ISO) |
| G-8 | revision `2026-10-custom-iso` refused by the command until R15–R17 are green (`CustomIsoReadiness`, I-R9); not part of the launch under decision 3 B |
| G-9, G-10 | rules in `config/loyalty.php`; the scheduler entry exists — verify with `schedule:list` on the host |
| G-11, G-12 | step 9 |
| G-13 | step 10 |
| G-14, G-15 | done in code, nothing to switch |

## 5. Pre-launch variant for the Comgate review (decision 2, recommended A)

Comgate reviews a fully functional deployed site before it issues the test merchant account (I-R10). Recommended variant
(`comgate-merchant-review.md` §2): **the production codebase on the real domain, before the launch**, with:

1. steps 1–5 and 12 of section 2 done (the real legal entity in the database, staff MFA, the doctor without FAIL);
2. the public site complete for the review: packages J4 (footer with identity and documents, only real payment methods, the VAT
   sentence and activation time, a clean answer to a card order without gateway credentials) and J7 (browser check) merged and
   deployed, the rows of `comgate-merchant-review.md` §1 marked HAVE or with the owner's answer;
3. ordering open only by bank transfer (or for the reviewer's demo account that the owner creates and sends to Comgate himself; its
   password never reaches the repository, a ticket or a chat); `PAYMENT_GATEWAY` without credentials so a card order answers the
   clean refusal of C-R10e;
4. no test banner, no demo data: `onhost:production:prepare --purge-dev-accounts` was run, `ONHOST_UI_DEMO=false`;
5. after the review: the owner stores the test merchant credentials on the server himself (`php artisan onhost:secrets:set`), then
   package J11 (connection check, 1 CZK test payment, recorded fixtures, manual card test on staging).

This **is a production deployment**: its own "ano", after P1 (staging proved the same SHA).

## 6. Publishing the legal texts (decision 6)

1. The lawyer answers `docs/legal/LEGAL_REVIEW_2026-10.md` (the owner forwards the desktop pack); the answer becomes package J10
   (draft `2026-10` adjusted, the complaints rules, seams).
2. **Staging first:** `php artisan onhost:legal:publish 2026-10 --effective-from=<YYYY-MM-DD> --approved-by="<name, date>"` (dry run),
   then the same with `--apply`. Check `/dokumenty/*` and the checkout consents on staging.
3. **Production:** the same command on the production host, with its own "ano", after step 4 (the entity in the database: the
   command refuses a missing entity, an empty or no-reply contact and a missing phone).
4. Until then I-R4 B holds: the launch may run on `2026-09`; L-03, L-04, L-10, L-14 are done in code before the launch whatever the
   lawyer says (B4 row of the checklist).

## 7. What the staging rehearsal must hand to this plan

- the frozen SHA and the gate's verdict on staging (rows of `expected-nonok` with their reasons — production has no such file: a
  non-OK row there is a FAIL unless the signed tag accepts it);
- the doctor after R23 (the rows that are WARN on staging because staging is staging — APP_ENV, egress — and must be OK in production);
- the R14 run of `--legal` (proves step 4), R10 (TOTP, step 5), R6/R7 (revisions, step 6), R11 (rates), R12 (tokens), R21 (webhooks),
  R22 (dead letters);
- the Penpot E2E result (decision 8) and what was skipped with its reason (R17–R19 without Proxmox, R25 read only).

## 8. Go/no-go, DNS cutover and rollback (phase R)

### Go/no-go (the owner signs it in `PRODUKCE-prikazy-2026-10-13.md` before step 3)

| # | Condition | Evidence |
| --- | --- | --- |
| G1 | Staging ran A–C of `<R-SHA>`, gate passed, doctor without unexpected FAIL | protocol `staging-rehearsal-<date>.md` |
| G2 | CI green on `<R-SHA>` (pest, pest-postgres, php85, monitoring, restore-drill, deploy-scripts, e2e smoke, security) | the run of the merge commit |
| G3 | PR-1 … PR-17 answered or their step skipped with a reason | SEZNAM |
| G4 | Signed tag on `<R-SHA>`, the owner's key in `allowed_signers` (PR-9) | `git tag -v <tag>` on the host |
| G5 | Backup bucket configured off the server (PR-10) and one restore drill passed on the host (step 2a) | doctor rows *platform backup …* OK |
| G6 | TTL of the records that change lowered to 300 s at least 48 h before (P-R13) | `dig +noall +answer onhost.cz` shows 300 |
| G7 | Comgate: either the pre-launch for the review (§5) or the launch with transfers only (`PAYMENT_GATEWAY` without credentials) | the decision in the sheet |
| G8 | A second person with TOTP for four eyes, or `ONHOST_FOUR_EYES=false` with the 24 h time lock (P-R11) | doctor *who decides approvals* |

### DNS cutover (owner, WEDOS; P-R13)

1. **T-48 h:** TTL 300 on `onhost.cz`, `www`, and the records that will point at the new host; nothing else changes.
2. **T-0 (after step 12 is green on the host itself, through `--resolve`):** A (and AAAA only if the host answers on IPv6) to the
   production address (PR-7); `curl -sI https://onhost.cz/up` from outside returns 200 and one HSTS header.
3. **T+24 h:** TTL back to the usual value.
Rollback: the previous records (written down at T-48 h in the sheet) — DNS is the only step that needs no deploy to undo.

### Rollback

The triggers and their time limits are [first-day-production.md](first-day-production.md) *Rollback triggers*; the commands are
[release-and-rollback.md](release-and-rollback.md) *Rollback*:

- **Code:** `REF=<last good tag> EXPECTED_SHA=<sha> DEPLOY_OPERATOR=<name> /usr/local/sbin/onhost-deploy` (backup not skipped).
- **Database** (only a migration that corrupted data, owner's approval, units stopped): a fresh backup first
  (`php artisan onhost:platform:backup && php artisan onhost:platform:backup:verify`), then `pg_restore --clean --if-exists` from the
  pre-release set the deployer printed; reconcile with `php artisan onhost:provisioning:reconcile` and `php artisan onhost:bank:sync`.
- **Stop the queues:** `systemctl stop onhost-scheduler.service 'onhost-queue@*'` (the site keeps answering; nothing runs behind it).
- **Freeze provisioning:** `POST /v1/staff/provisioning/freeze` (staff console *Infrastruktura*), thaw after the fix.
- **DNS:** the records written down at T-48 h.
Money and audit rows are never rolled back; they are corrected with new documents and postings.
