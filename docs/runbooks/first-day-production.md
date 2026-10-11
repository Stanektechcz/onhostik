# The first day in production

Hour by hour, what the operator looks at on the first day after the release of go-live (G10, TASK-0119). It starts where
[go-live-checklist.md](go-live-checklist.md) (sections 0 and 10) ends: every step there is done, the final doctor run had 0 FAIL, and the
owner's signed tag is deployed by `onhost-deploy` ([release-and-rollback.md](release-and-rollback.md)). **This page was prepared
and has not been run**: the times are offsets from the minute the deploy printed `OK` (call it **H0**), the commands are the ones the
repository ships, and what is *assumed* is named as such.

Rules for the day: two people are reachable (the owner and one operator; the on-call rota is in the staff console), nothing is
"fixed" by editing rows (money and audit rows are corrected with new documents, never rolled back), no step prints a password, a
token or a signing secret into a ticket or chat, and every number below is read, not guessed. Write the time and the result of each
check in the incident log of the day (`POST /v1/staff/incidents` for anything that deserves a record).

## Before H0 (the evening before)

* The release record exists (`.ai/releases/<date>-<sha7>.md`) and the tag is the owner's signed one; the previous good release and its
  backup set are named on the page you keep open (the deployer prints them: `last-good.json`, `runs/<ts>/backup.out`).
* Prometheus loads `infra/monitoring/slo-alerts.yml`, Alertmanager routes `severity: page` to a person and `ticket` to the queue
  (checklist G-13); the on-call rota has a name in it (`OnhostNobodyOnCall` is the alert for the opposite).
* Deploy outside business hours: whether Comgate retries a payment callback that got a 503 during the maintenance window is
  **ASSUMED no** (staging-launch.md, question 12) — which is why the payment check below reconciles by hand.

## H0 to H+1 — it is up and it is what we released

| When | Look at | How | Expected | If not |
| --- | --- | --- | --- | --- |
| H0 | The public check and readiness | `/up` answers; `/healthz` answers `ok` for database, cache and the outbox | all green | the deployer already stopped at exit 6 or 7 and printed the recovery command; do not improvise past it |
| H0 | **The doctor, in full** | `php artisan onhost:doctor` (and `--json` into the day's log) | 0 FAIL. WARNs are the ones the checklist named, each with its remedy in the row. **New in Phase G:** *VAT payer mode agrees with the legal entity*, *custom ISO virus scan passes its self-test*, *custom ISO storage has room*, *loyalty expiry runs and balances are sane*, *rotated webhook secrets overlap only briefly*, *no outbox dead letters* | a FAIL row names its remedy; a FAIL that you cannot clear in 30 minutes is a rollback trigger (below) when it is blocking |
| H0 | The units | `systemctl status onhost-scheduler 'onhost-queue@*'` — `default`, `mails`, `webhooks`, `provider-proxmox`, `provider-ispconfig`, … all `active` | active | `journalctl -u <unit> -n 100`; the doctor rows *scheduler running* and *queue worker alive* say the same |
| H0 + 10 min | **Money mode** | the doctor rows for the payment gateway: Comgate is **not** in test mode (`COMGATE_TEST=false`), the callback allow-list is set, the legal entity IBAN is real | not test | stop selling: freeze new orders in the staff console until the mode is right; a test-mode gateway taking real orders is a rollback trigger |
| H0 + 15 min | **VAT mode of the first document** | `php artisan onhost:vat:payer-mode` exits 0 and shows the mode the owner decided (G-3) | the mode, the legal entity and the tax rules agree | do not let the first order through before this agrees: a document keeps the seller it was frozen with |
| H0 + 20 min | A real smoke order by the owner (the cheapest plan, a real card) | checklist section 5, smoke tests | order → payment → provisioning → invoice and the right kind of document for the VAT mode | a failure here is the first thing the rollback triggers below look at |
| H0 + 30 min | Metrics reach Prometheus | `onhost_outbox_dead_letters`, `onhost_outbox_pending_oldest_seconds`, `onhost_queue_jobs_failed_total`, `onhost_provider_health`, `onhost_virus_scanner_up`, `onhost_platform_backup_verified_timestamp` are returned by a query | present; dead letters 0 | scrape target and `/metrics` access; no alert can fire from a metric nobody collects |

## H+1 to H+4 — the lanes that carry the money and the events

| When | Look at | How | Expected | If not |
| --- | --- | --- | --- | --- |
| H+1 | **Outbox dead letters** | doctor row *no outbox dead letters*; `php artisan onhost:outbox:dead-letters` | none | read the last error of each (redacted), fix the listener, `onhost:outbox:dead-letters --requeue` ([outbox-dead-letters.md](outbox-dead-letters.md)). A dead letter among `invoice.*`, `payment.*` or `order.*` events is a money problem: treat it as one |
| H+1 | **Outbox lag** | `onhost_outbox_pending_oldest_seconds` below 120; `/healthz` outbox check ok | below 120 s | `systemctl status onhost-queue@default`; `php artisan onhost:outbox:relay` once by hand |
| H+1 | **Payments and Comgate reconciliation** | every payment intent of the day against what Comgate shows in its merchant portal (each paid intent has one posting with key `payment:<intent>`); `POST /v1/payments/{intent}/sync` re-reads a single intent; the staff page *Nastavení → Bankovní platby* lists bank lines and `GET /v1/staff/payments/bank` the pending ones; `onhost:bank:sync` runs every five minutes once a bank is configured | every paid intent is posted once; none is paid at Comgate and open here; no `finance.reconciliation.mismatch` | **never edit a posting**: post a correcting transaction that names the statement line ([billing-dunning.md](billing-dunning.md), *Reconciliation*). A callback that was lost in the deploy window is re-read with `…/sync` |
| H+2 | **Webhook lane** | the queue unit `onhost-queue@webhooks` is `active`; staff or a test endpoint of the owner's: `POST /v1/webhooks/{endpoint}/ping` and `GET …/deliveries` show a delivered attempt; the doctor row *rotated webhook secrets overlap only briefly* is OK | delivered, no endpoint suspended | a suspended endpoint is the customer's (`POST …/enable` after they fixed it); a lane with no worker is not an error — deliveries use the default lane — but a pile on `webhooks` with the unit down is |
| H+2 | **Dunning, read first** | there is no `--dry-run`: the read-only look is `GET /v1/staff/dunning` and `onhost_dunning_open`. On the first day there must be **no** open case older than the ladder's first notice. Run `onhost:billing:dunning` (daily 06:00) or `POST /v1/staff/dunning/run` (fresh step-up) only after the report agrees with what you expect | no cases, or only the ones you can explain | the run can suspend and terminate: if the report shows a case you cannot explain (an invoice paid but not settled, mail not leaving — the tick refuses to suspend while `MailHealth` fails), stop and look at the invoice first ([billing-dunning.md](billing-dunning.md)) |
| H+3 | **Provisioning** | `onhost_operations_total{state="DEAD"}` unchanged, `onhost_operations_backlog` under its threshold, `php artisan onhost:provisioning:reconcile critical` finds no drift ([provisioning-queue.md](provisioning-queue.md)) | no DEAD saga, no drift | `POST /v1/staff/provisioning/freeze` stops new provider mutations while you diagnose; thaw afterwards |
| H+3 | **Providers** | `onhost_provider_health{state="down"}` is empty; `onhost_registrar_credit_czk` is above its alert floor | up, credit enough | [provider-outage.md](provider-outage.md) |
| H+4 | **Custom ISO (only if G-8 was applied)** | `php artisan onhost:isos:scanner-check --fresh` OK (`--fresh` forgets the answer kept for minutes); the storage row shows free space above one organization's quota | OK | uploads are refused with 503 while the scan fails — that is the safe state; fix clamd, do not bypass it |
| H+4 | **Loyalty** | the doctor row is OK and `php artisan schedule:list` still shows `onhost:loyalty:expire` at 05:35 | OK | a negative balance or debt is a bug: an incident, a correction through a staff award or clawback with a reason, never an edited row |

## H+4 to H+24 — the overnight jobs and the morning after

| When | Look at | How | Expected | If not |
| --- | --- | --- | --- | --- |
| Evening of day one | **The doctor again**, then compare with H0 | `php artisan onhost:doctor --json` | the same rows OK; nothing new | any new non-OK row is the first lead for tomorrow |
| Evening | **Error budget** | `GET /v1/staff/reports/slo` — no `OnhostBurnRate*` alert; the policy for `portal` and `payments` is not `freeze` | within budget | a freeze blocks releases except the fix for its own cause |
| Overnight | The scheduler's night jobs ran | `onhost:fx:sync` (06:10), `onhost:platform:backup` and its verification (the doctor row for the backup disk, `onhost_platform_backup_verified_timestamp` newer than 24 h), `onhost:registrar:reconcile` (04:00), `onhost:rebalance:plan` (03:35) | all ran; the backup is verified | `OnhostBackupStale` pages; run the backup by hand and verify it before anything else |
| 05:35 next morning | The first loyalty expiry | `onhost:loyalty:expire` ran (no points are due yet: the programme starts at `counted_from`), warnings 30 days ahead | the doctor row stays OK | the schedule, not the data, is the first suspect |
| 06:00 next morning | **The first dunning tick** | the cases it touched: `GET /v1/staff/dunning` before and after; the notices that left appear in the mail outbox | only what you expected | see H+2 |
| Morning after | Customer-visible facts | support inbox, the first invoices (kind of document matches the VAT mode, series, numbers without gaps), the first proforma, the first wallet top-up | consistent | a wrong document is **not** edited: it is cancelled or credited with a new document |

## Rollback triggers

Decide with the owner; **there is no automatic rollback** and money and audit rows are never rolled back. Roll the code back when one
of these holds and cannot be cleared inside the time named:

| Trigger | Within | What to do |
| --- | --- | --- |
| `/healthz` or `/up` red, or 5xx on the portal above the fast burn-rate alert (`OnhostBurnRateFast`) | 15 minutes | roll back the code: `REF=<last good sha or tag> EXPECTED_SHA=<sha> DEPLOY_OPERATOR=<name> /usr/local/sbin/onhost-deploy` (release-and-rollback.md, *Rollback*) |
| A blocking doctor row FAIL that the remedy does not clear (VAT mode disagreement, money in test mode, a scanner that fails with custom ISO on sale) | 30 minutes | for the VAT mode before the first document and for money mode: freeze new orders, fix, thaw; otherwise roll back the code |
| Payments taken and not posted, or posted twice | at once | stop sales (freeze), do **not** roll back the database, reconcile by hand with correcting postings; roll back the code only if the cause is the new release |
| A migration corrupted data | at once | only with the owner's approval and the units stopped: the database restore of release-and-rollback.md, with a fresh backup of the current state taken first; then `onhost:provisioning:reconcile` and `onhost:bank:sync` |
| Provisioning produces DEAD sagas or drift on the first real orders | 30 minutes | `POST /v1/staff/provisioning/freeze`, roll back the code, thaw, retry the failed operations |
| Dunning wants to suspend or terminate a customer who paid | before 06:00 | do not run the tick; settle the invoice first; if the tick already ran, resume the service (`/v1/staff/services/{id}/reinstate` asks `billing.dunning.manage`) and write the incident |
| Custom ISO: an upload was accepted while the scan was down (should be impossible: fail closed) | at once | treat as a security incident ([incident-response.md](incident-response.md)); revert the catalogue revision of G-8 by publishing the previous plan versions |

What does **not** trigger a rollback: a WARN row with a remedy, an outbox dead letter whose listener is fixable (requeue it), a
suspended webhook endpoint (the customer's), a customer whose first payment is slow at their bank.

## End of the day

Write one paragraph into the incident log: the doctor at H0 and in the evening, the number of orders, payments posted against
payments taken, dead letters (found, cleared), anything frozen and thawed, and who was on call. The next day's first look is the
same page from *H+1* on, shortened to the doctor, the dead letters, the reconciliation and the 06:00 dunning tick; from day three the
standing monitoring (alerts, the staging report `php artisan onhost:staging:report --check` for a review without panel access)
takes over.
