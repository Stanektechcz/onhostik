# Breach register: the forensic look-back for the permission holes

Phase 0 of the permission program closed seven holes that had been open since launch (program §9: TD-1, PA-01, G1,
PA-02, PA-04, SS-1/SS-5 with EXPL-1..3, P1/P2). Closing a hole says nothing about the time **before** it was closed.
This runbook answers two questions for each hole: did anybody use it, and what do we do if they did (program key
P0-01 / IF-0, decision D16).

```
php artisan onhost:forensics:lookback [--since=2026-01-01] [--until=2026-09-27]
                                      [--source=owner_demotion --source=... ] [--json]
```

| Exit code | Meaning |
| --- | --- |
| 0 | No source has a hit. Unknowns may still be listed; read them. |
| 1 | At least one source has a hit. |
| 2 | Bad options (unparsable date, `--since` after `--until`, unknown source). Nothing was read. |

## What it does, and what it never does

* **Read-only.** It runs SELECTs inside a database transaction that is always rolled back. It dispatches no
  CommandBus command, resolves no provider adapter, **calls no panel** (no aaPanel, ISPConfig, Pterodactyl, Proxmox,
  Discord, bank or registrar), writes no file and publishes no event. It has no schedule entry and no route. The test
  `tests/Feature/Platform/ForensicLookbackTest.php` ("changes nothing") checks this: only selects reach the database,
  every table count is unchanged, and no HTTP request is sent.
* **Output goes to stdout only.** You get a table by default, or JSON with `--json`. The report carries ids, masked
  IBANs (`CZ65…5399`) and, for PA-02, only the offending path. It never contains e-mail addresses, names, full IBANs,
  file contents or the rest of a scheduled command.
* **It decides nothing and sends nothing.** Whether a hit is a personal-data breach, and whether anybody is told, is
  the owner's decision (§10 O3, below).

## Running it on production

1. Only the operator runs it, on the application host as the deploy user (the same shell as `php artisan doctor`).
   It needs no credentials beyond the application's own database connection. It is just as good, and preferred when
   the database is large, to run it against a **restored copy** of the production database (the latest backup
   restored into an isolated instance, `APP_ENV=production`, no queue worker, no scheduler). The traces are the same.
2. Run the whole history first, with no `--since`. The window only limits event-based sources. Memberships made
   before the window still count, and standing state (PA-01 panel users, TD-1 current roles) is always read in full.
   ```
   php artisan onhost:forensics:lookback --json > /root/forensics/lookback-$(date +%F).json
   ```
   Keep the file **outside the web root and outside `storage/app/public`**, readable only by root (`chmod 600`). It
   contains organization, user, service and payout ids. Record its SHA-256 (`sha256sum`); the file itself leaves the
   host only as evidence attached to the cyber incident (below). Delete it once the review is closed and the evidence
   is attached.
3. Read `coverage` first. It gives the number of audit and operation rows and the oldest one. If the oldest row is
   younger than the hole (audit retention, a restore), everything before it is **unknown, not clean**. Write that
   in the register entry.
4. Re-run one source with `--source=<key>` while you follow up a hit. Re-run the whole report after every Phase 0
   branch is deployed: the report must not grow afterwards.

## Reading the report

Each source row lists `checked` (table and row count), `hits`, `unknowns`, `notes` and `limits`. The verdict is:

* **HITS**: the stored data shows the trace of the exploit. `confidence` is:
  * `confirmed`: the row itself is the trace;
  * `possible`: the data allows an innocent reading, such as a partner's own new IBAN;
  * `attempt`: the operation was refused or failed. It still shows intent.
* **UNKNOWNS**: no hit, but part of the question cannot be answered from the database. Every unknown says why and
  which tool answers it.
* **CLEAN**: nothing found and nothing left open. The fixed caveats in `limits` still apply.

| Source key | Exploit | Hit kinds | Follow-up for hits and unknowns |
| --- | --- | --- | --- |
| `owner_demotion` | TD-1 | `owner_accepted_lower_role`, `owner_attached_lower_role`, `owner_membership_not_owner` | Look at the organization's audit trail around the audit event id. Was the owner the person who clicked, or was the invitation sent to their address by somebody else? |
| `game_panel_identity` | PA-01 | `panel_user_of_other_org` (confirmed), `panel_user_shared_across_orgs` (possible) | Services on a panel user the platform did not record creating are unknowns. The Pterodactyl identity dry-run of TASK-0033 reads the panel's `external_id` for them (read-only). |
| `discord_after_removal` | G1 | `discord_used_after_removal`, `discord_command_after_removal` | Reads leave no audit row: `last_used_at` proves a use after the removal, not what was read. Links still open after a removal are listed in `notes`; TASK-0035 revokes them. |
| `aapanel_outside_root` | PA-02 | `path_outside_root` (confirmed or attempt), `cron_reaches_outside_root` (possible) | Symlinks and archive contents live only on the node. The aaPanel tenancy dry-run of TASK-0034 (`operator:aapanel:tenancy`, read-only) looks there. Never open or run a file you find; copy it with its hash into the evidence store. |
| `token_cross_org` | PA-04 | `token_used_on_other_org` (grouped per token and organization) | Only writes are audited. The token's owner, and what the actions changed, come from the listed audit ids. |
| `staff_own_org` | SS-1, SS-5 (EXPL-1..3) | `staff_hold_lift_own_org`, `staff_force_purge_own_org` | Compare with the approval record and the reason in the audit row. A staff member acting on their own company without a second person is a hit even when the action was right. |
| `partner_payouts` | P1, P2 | `payout_above_allocated` (confirmed), `payout_iban_changed` (possible) | Finance checks the bank statement. Only the partner's written confirmation proves that a new IBAN is theirs. Do not contact the partner before the owner has decided (the partner may be the actor). |

## Recording findings

Every run is recorded, including a clean one: the register is the evidence that the look-back was done.

1. Open a **cyber incident** for the look-back, whether or not there are hits:
   `POST /v1/staff/security/incidents` with a title (`Permission holes Phase 0: forensic look-back`), severity and,
   only if a hit touches personal data, `personal_data_breach: true`. That flag starts the GDPR 72-hour timer
   (`GDPR_72H`, `GET /v1/staff/compliance/timers`). The clock runs from the moment we became **aware** of a breach,
   meaning a confirmed hit that concerns personal data. It does not run from the date of the exploit.
2. Attach the report as evidence: `POST /v1/staff/security/incidents/{case}/evidence` with `name`, `sha256` (from
   step 2 above), the private `path` and `legal_hold: true`. Attach every later re-run the same way.
3. Add one register entry per hit, or one per source when the source is clean, to the incident case:

   ```
   Source / exploit:     partner_payouts / P1
   Hit (kind, ids):      payout_above_allocated, payout 81, partner 12, excess 1 000,00 CZK
   Confidence:           confirmed | possible | attempt | unknown (why)
   Checked:              tables and row counts from the report; coverage window
   Personal data:        which categories, how many data subjects (ids only, no names)
   Assessment:           exploited? by whom (actor id)? since when? ongoing? (the fix commit/branch)
   Follow-up done:       TASK-0033/0034/0035 dry-run output hash, bank statement, approval record …
   Owner decision:       date, decision (notify authority / notify customers / no notification), reason
   Notifications:        draft ids; sent on <date> by <owner>, or "not sent — owner decided <reason>"
   ```

4. The acceptance of TASK-0038 is one row per verified exploit, each with sources checked, hits and unknowns, and
   the **owner's decision recorded**. A register without the owner's decision line is not finished.

## GDPR Art. 33 and 34: drafted, never sent without the owner (§10 O3)

When a confirmed hit concerns personal data (another customer's files, mailboxes, invoices, contacts, service
secrets), the operator **prepares** the notifications. The owner decides whether they are sent:

* **Art. 33: notice to the supervisory authority** (Úřad pro ochranu osobních údajů, ÚOOÚ). This is due within
  72 hours of awareness unless the breach is unlikely to result in a risk to people. If it is late, the notice must
  say why. Draft it with the nature of the breach, the categories and approximate number of data subjects and
  records, the contact point, the likely consequences, and the measures taken (the Phase 0 fix) and planned.
* **Art. 34: notice to the affected customers.** This is due without undue delay when the risk to them is high.
  Write it in plain language: what happened, what it means for them, what we did, what they should do (for example
  rotate a password, check a site), and whom to contact.

Rules:

* Every draft starts with the line **`DRAFT, not sent, awaiting the owner's decision (§10 O3)`**, is stored only in
  the incident case, and names no other customer.
* **Nobody sends either notice, and nobody submits the `GDPR_72H` timer**
  (`POST /v1/staff/compliance/timers/{id}/submit`), without the owner's explicit decision recorded in the register
  entry. This applies to the command's author, the operator on duty and any AI session. An AI session prepares
  drafts only; it never sends mail and never calls the submit or waive endpoints.
* If the owner decides not to notify, record the reason in the register entry: the risk assessment is part of the
  Art. 33(5) documentation duty. Then waive the timer with that reason
  (`POST /v1/staff/compliance/timers/{id}/waive`). The owner does this, or the operator on the owner's recorded
  instruction.
* If the 72 hours run out while the owner is unreachable, tell the owner again through every channel you have and
  write the attempts into the entry. Still do not send.

## Related

* [provider-calls-audit.md](provider-calls-audit.md): the same kind of look-back for the ISPConfig remote-id hole
  (TASK-0005).
* [incident-response.md](incident-response.md): public incidents and the cyber-incident clocks.
* [compliance-requests.md](compliance-requests.md): timers and data-subject requests.
* [security-boundaries.md](security-boundaries.md): the boundaries the Phase 0 fixes restore.
