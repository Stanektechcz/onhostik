# Production launch plan (phase J, 2026-10-11)

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

## 1. Preconditions (none of them is met on 2026-10-11)

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
| 3 | **Deploy the owner's signed tag through the gate** | `REF=<tag> EXPECTED_SHA=<40-hex> DEPLOY_OPERATOR=<name> PHP_FPM_RELOAD='<reload>' /usr/local/sbin/onhost-deploy; echo rc=$?` | `rc=0`; doctor 0 FAIL except rows the tag's `Accept-Gate:` names (none expected); migration `000950` passes (B7: a genuine double commission stops it — then the J5 cancellation, not an edited row) | `REF=<last good> EXPECTED_SHA=<sha> … onhost-deploy` (the deployer refuses a downgrade of itself, not of the app) | step 17 gate, B7 |
| 4 | **Legal entity into the database** (B2, G-2): the config defaults are the real data (I-R11, I-R15) | `php artisan onhost:production:prepare --legal` | doctor *legal entity …* rows OK; `onhost:vat:payer-mode` exits 0 (not a payer, H-R0) | the entity is data: correct with the same command and the right values before the first document; after it, only new documents change | step 11, G-1…G-3 |
| 5 | **Staff authenticators** (B3): the owner enrols every staff account himself and types every code | `php artisan onhost:staff:totp <email>` then `… --code=<6 digits>` per account | *staff and demo accounts have an authenticator* OK (0 missing) | `--reset` for a lost device | step 6 |
| 6 | **Catalogue revisions** (dry run, then apply; four eyes unless `ONHOST_FOUR_EYES=false`) | `php artisan onhost:catalog:revise`, then `--apply` per revision; **not** `2026-10-custom-iso` (I-R9: only after R15–R17; decision 3 B = launch without it) | *every catalogue revision is applied* OK except custom ISO (deliberately) | a new plan version, never an edit | steps 4, 13 |
| 7 | **Exchange rates** | `php artisan onhost:fx:sync` | *exchange rates are fresh* OK | none (data) | step 5 |
| 8 | **API tokens name an organisation** (R9): look, tell holders, then switch | `php artisan operator:tokens:unbound --dry-run`, then `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true` + `config:cache` | *API tokens must name an organisation* OK | the switch back + `config:cache` | step 10 |
| 9 | **Webhook lane and secret overlap** | `systemctl enable --now onhost-queue@webhooks`; `ONHOST_WEBHOOK_SECRET_OVERLAP_MINUTES` (default 60) | *rotated webhook secrets overlap only briefly* OK | stop the unit (default lane takes over) | step 15, G-11, G-12 |
| 10 | **Dead letters are heard** (B12) | `promtool check rules infra/monitoring/slo-alerts.yml`, load into Prometheus, Alertmanager routes for `ticket` and `page` | `onhost_outbox_dead_letters` returned by a query; *no outbox dead letters* OK | remove the rule file | step 16, G-13 |
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
| B7 | `000950` stops on a genuine double commission | blocking if found | the commission-only cancellation is package J5 (not merged on 2026-10-11) | J5 merged before step 3, so finance can cancel the newer row through the bus |
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
