# Day two and after: the standing routine

What the operator does every working day, every week and every month once production runs (P17, phase Q). Day one is
[first-day-production.md](first-day-production.md); this page takes over from its *End of the day*. Every command below exists in
this code base (`tests/Feature/Docs/RunbookCommandsTest.php` fails on a renamed or removed one). **Prepared, not yet run in
production**: what is assumed is named as such.

Who and when (owner decisions): support and these checks run **Mon–Fri 08:00–17:00 Europe/Prague** (P-R37); the owner imports
the bank statement every working day (P-R45, `billing.reconcile`); alerts reach the owner by e-mail with the second person of
PR-5 as stand-in (P-R40 — the address and the monitoring machine are still open: PR-13, OP-4). Nothing here edits a money or
audit row: corrections are new documents and postings.

Shell helpers on the app host (as in the staging documents):

```bash
APP=/www/wwwroot/<site>; P=/www/server/php/85/bin/php
art() { (cd "$APP" && HOME=/tmp setpriv --reuid=www --regid=www --init-groups -- $P artisan "$@"); }
```

## Every working day (morning, about 20 minutes)

| Look at | How | Expected | If not |
| --- | --- | --- | --- |
| **The doctor** | `art onhost:doctor` (and `art onhost:doctor --json` into the day's log) | 0 FAIL; the WARNs are the known ones, each with its remedy | a new non-OK row is the day's first task; a blocking FAIL follows the rollback triggers of the first-day page |
| **Scheduled jobs** | doctor row *every scheduled job ran within its budget* (P14; the metric `onhost_schedule_last_success_seconds`) | OK | run the named job by hand (`art <command>`), read its error, `journalctl -u onhost-scheduler -n 200` |
| **Outbox dead letters** | doctor row *no outbox dead letters*; `art onhost:outbox:dead-letters` | none | fix the listener, then `art onhost:outbox:dead-letters --requeue` ([outbox-dead-letters.md](outbox-dead-letters.md)); `invoice.*`, `payment.*`, `order.*` are money |
| **Bank statement (owner)** | download yesterday's camt.053 from George and import it in the staff console (*Finance → Bankovní výpisy*, `billing.reconcile`); then the list of unmatched lines | every incoming payment matched to its proforma by the variable symbol | an unmatched line is matched by hand with a reason, never by editing a posting ([billing-dunning.md](billing-dunning.md), *Reconciliation*). The VS position in camt.053 is still **ASSUMED** until BK-2 |
| **Card payments** | the payments of the day against the Comgate portal; `POST /v1/payments/{intent}/sync` for one that looks open | each paid intent posted once | see the first-day page, *Payments and Comgate reconciliation* |
| **Dunning before the 06:00 tick of tomorrow** | `GET /v1/staff/dunning`, metric `onhost_dunning_open` | only cases you can explain | a case for a customer who paid: settle the invoice first (the tick suspends and terminates) |
| **Support and complaints** | tickets in the staff console; a complaint (*reklamace*) has a **30-day legal deadline** (L-04, reklamační řád) | every complaint answered well inside 30 days | escalate to the person who handles complaints (P-R38) |
| **Alerts of the night** | Alertmanager mails / on-call alerts in the staff console (*Incidenty → On-call*) | each acknowledged with a note | an open page is today's incident ([incident-response.md](incident-response.md)) |

## Every week (Monday)

| Look at | How | Expected | If not |
| --- | --- | --- | --- |
| **Platform backup and its verification** | doctor rows for the platform backup; `art onhost:platform:backup:verify` once by hand | a verified set younger than 26 h, off the server once PR-10 is in place | `OnhostBackupStale`; run `art onhost:platform:backup` and verify before anything else ([backups.md](backups.md)) |
| **Certificates** | `art onhost:certificates:watch` (one handshake to each site's own node, writes nothing but its findings) | nothing expiring within 10 days | renewal starts at 10 days; a failure names the site |
| **Panel versions** | `art onhost:integrations:versions` | every panel on a verified version | a held panel sells nothing new; accept a version only after its checks pass ([panel-upgrade.md](panel-upgrade.md)) |
| **Capacity** | `art onhost:provisioning:capacity-forecast`, `art onhost:capacity:basis`, the nightly `onhost:rebalance:plan` mail | no pool running short within the forecast window | qualify a new node (`art onhost:nodes:qualify`) or stop selling the plan |
| **Usage against plans** | doctor rows for usage; the mails of `onhost:services:usage-watch` (85 % warning, 95 % next plan where allowed) | no service silently over its plan | a manual limit raise is **billed** (owner rule "measure everything"): `art onhost:limit-raise` through the bus, never a hand edit |
| **Registrar** | metric `onhost_registrar_credit_czk`, `art onhost:registrar:reconcile` result in the doctor | credit above the floor, no drift | top up WEDOS; a domain gone from the registrar is closed by the reconcile |
| **Tokens and access** | `art operator:tokens:unbound --dry-run`, `art onhost:access:review` | nothing unexpected | tell the holder; revoke through the staff console |
| **Failed jobs and the backlog** | metrics `onhost_queue_jobs_failed_total`, `onhost_operations_backlog` | flat | [provisioning-queue.md](provisioning-queue.md) |

## Every month (first working day)

| Look at | How | Expected | If not |
| --- | --- | --- | --- |
| **Restore drill** | as root: `bash infra/aapanel/restore-drill.sh` (the application's DB role in `PGUSER`, password typed) restores the newest platform backup set into a throw-away database, runs the control queries and drops it ([backups.md](backups.md), *Restore drill of the platform backup*) | exit 0; the doctor row *platform restore drill within 35 days* is OK | a backup that cannot be restored is not a backup: an incident |
| **Customer backups restore test** | the doctor rows of `onhost:services:restore-test` (it runs nightly and asks who is owed one) | every service due had its test | [backups.md](backups.md) |
| **Accounting documents** | CSV of the month's documents and payments for the accountant (P-R36; the export is a phase-R package — until then the staff reports) | sent by the 10th | — |
| **Metering after launch** | `art onhost:metering:preview` before any switch of the harmonogram P-R24 (each switch has its own "ano") | the preview matches what the switch should do | do not switch |
| **Legal documents** | a new version of the terms is announced 30 days ahead (14 for the privacy policy): `art onhost:legal:publish <version>` previews; `--apply` only with the owner's approval and the lawyer's answer | customers notified on time | — |
| **Partner payouts** | `onhost:partners:auto-payouts` runs on the 1st at 06:00; check the payouts in the staff console before they leave | each payout once, to the right account | stop it in the console; a duplicate is a storno, never an edited row |
| **Mail backup retention** | `art onhost:mail:backup-retention` (without `--apply` it only lists) | as decided (P-R51) | `--apply` only with the owner's "ano" to the switch |
| **Tests on the clock** | the CI on `development` is green also between 22:00–24:00 and 00:00–06:00 UTC (time-of-day bugs: TASK-0182, TASK-0192) | green | a red run that is a time bomb is fixed first |

## What is still open (from SEZNAM)

PR-13 and OP-4 (where monitoring runs, who gets the alerts), PR-10 (off-server backup storage), BK-2 (the camt.053 sample that
verifies the VS position), PX-3/PX-4 (backup switches after launch), LG-1/LG-2 (lawyer and dates for the 2026-10 texts).
