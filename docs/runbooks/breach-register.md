# Breach register: the forensic look-back for the permission holes

Phase 0 of the permission program closes nine holes that had been open since launch (program §1 and §9: TD-1, TD-2,
TD-3, PA-01, G1, PA-02, PA-04, SS-1/SS-5 with EXPL-1..3, P1/P2). PA-04 and SS-1/SS-5 with EXPL-1..3 close with TASK-0039,
rebased onto waves 1 and 2 in the final Phase-0 chain; what it ships only as a shadow release behind a switch, and one PA-04
read the last red-team check found open, are listed under "Still open after Phase 0 wave 2" below; what Slice 1 (TASK-0042) left open, under "Still open
after Slice 1".
Closing a hole says nothing about the time **before** it was closed.
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

## Still open after Phase 0 wave 2

The P0-16 red team of the stacked wave-two chain (TASK-0041, 2026-09-27) found holes of program P0-08, P0-09 and P0-14 still
exploitable there, because their fix, TASK-0039, was in neither wave. TASK-0039 is now rebased onto that chain, the pins of
`tests/Feature/Security/PhaseZeroOpenItemsTest.php` went red for the right reason and were replaced by its proofs, and these
lines were struck: `EXPL-1`/`EXPL-2`/`EXPL-3` and `SS-1` (a member of staff on a customer route is the customer), `SS-14` (the
console pre-flight), `credit.maySpend` (the reinstatement credit gate), `SS-5`/`SE-3` and `backup.delete` (CRITICAL
`staff.service.delete`, staff reach on a customer CRITICAL key CRITICAL) and `SS-4`/`PA-06` (`PanelLoginCommand`).
The P0-16 re-check of the final chain found `EXPL-1..3` and `SS-1` moved, not closed: `/v1/staff/services/{id}/actions` and
`…/reinstate` asked the customer keys, so any staff account that was a member or a share guest of a service (an auditor, an IAM
admin) lifted ONhost's holds and undid a refunded cancellation there. TASK-0039 closes that on the chain: staff mode asks staff
keys (`staff.service.manage`, `staff.service.delete`, the staff billing key `billing.dunning.manage`), `StaffActor::may` asks
the key of a staff role, and a member of staff acting in staff mode in an organization of their own takes a second person or
the time lock (proofs: `StaffModeTest` "P0-16 re-check", `PayAndRestoreTest` "P0-16 re-check").

The first three entries below are written down but still **allowed** until the operator switches the rule on. Until then the
look-back for these is not a look-back: a clean report means only "not used yet", and a re-run after the Phase 0 deploy may
grow. The last entry, found by the same re-check, has no switch and no fix yet. Phase 0 is not signed off while this list has
an entry. Switch each rule on as its line says (or land the fix), then strike the line (the register test in
`PhaseZeroOpenItemsTest.php` reads this list).

* `IF-4` (P0-08, then P0-15, TASK-0039): a global staff role, or a JIT elevation, still reaches the customer keys it holds
  (`support.ticket.read`, `backup.delete`, `backup.restore`, …) in every organization. Each such allow is now written once per
  person, key, organization and day to `security_events` kind `authz.staff_reach`, and a customer CRITICAL key reached this
  way is CRITICAL. It is refused once `ONHOST_STAFF_REACH_ENFORCED=true`, which is due when
  `php artisan operator:authz:staff-reach --days=7` has stayed empty; P0-15 then takes the customer keys off the staff roles.
* `archive.restore` (IF-4, P0-08, TASK-0039): a global `backup.read`/`backup.restore` binding still passes
  `ServiceArchiveService::assertMayRestore` for every organization — one member of staff can restore an archive over a
  customer's live site. It is written to `authz.staff_reach` and refused with the same switch, `ONHOST_STAFF_REACH_ENFORCED`.
* `PA-04` (IF-5, P0-09, TASK-0039): a token bound to an organization acts for that organization only, and never with a staff
  role's global reach. A token stored with **no** organization (`personal_access_tokens.organization_id` empty) still acts
  for every organization its person belongs to, until `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true`: list them with
  `php artisan operator:tokens:unbound --dry-run`, tell the owners, then switch it on. Look-back source: `token_cross_org`.
  Runs queued with a token before this release carry no `desired.token_id` and finish on the person's full view even after the
  token is revoked; deploy when none is in flight, or let them finish first.
* `GET /v1/me` (PA-04, P0-09; P0-16 re-check on the final chain, MEDIUM, **no switch, no fix yet**): the route is open to
  tokens and returns every current membership of the token's person, each with the full organization record (billing e-mail,
  company and VAT ids, address, settings, the person's role there). A token bound to organization A reads organization B's
  data. Fix: answer a token with its own organization only, proven by a failing-first test in `TokenPrincipalTest`. The
  `token_cross_org` source does not see it (reads are not audited).

## Still open after Slice 1

Slice 1 of the program (S1-01/S1-02, TASK-0042, stacked on the Phase-0 chain) closes TD-7 and TD-9 and adds the owner
recovery (D21); its S1-07 red team (on `903ad07`) had four HIGH findings, fixed in `2856e3c`. What it leaves open is listed
here. Most entries are holes in the new code paths, which reach production with the Slice-1 deploy; TD-6 is older. Slice 1 is
not signed off while this list has an entry: fix each with a failing-first test (or turn the switch on), then strike the line.
There is no look-back source for these yet; the audit rows named in each entry are where to look.

* `TD-6` (I6, S1-02, TASK-0042; **written down, still allowed until the switch**): the active grants of a member who was
  removed or demoted — memberships, project roles, shares they gave — stay active. Each is recorded
  (`organization.grant.cascade.flag`, `organization.grants.unbacked`) and listed by `php artisan operator:grants:cascade --dry-run`;
  they are revoked, each after an access snapshot, only once `ONHOST_GRANT_CASCADE_ENABLED=true`, and only on the next loss of a
  grantor — the backlog before the switch is never re-examined, a cascade revocation chains through `removeMember`, and there is
  no grouped undo.
* `owner-recovery approver` (D21, R1-4, re-review of `df1f6d3`; MEDIUM, no fix): `OwnerRecoveries::assertNotParty` keeps a staff
  party out of opening and completing a recovery, but `ApprovalService::decide` asks the second person only "not the requester"
  and "holds `iam.mfa.reset`". The owner, the heir or a member of a reached organization who is also staff can approve it.
  Audit: `organization.owner_recovery.open` and the approval's `decided_by`.
* `owner-recovery transfer` (D21, S1-07; MEDIUM, no fix): a transfer-mode recovery makes the heir the owner with no acceptance and
  no step-up of the heir (the I4 two-step does not apply), and the heir's role is checked only at open (a guest is excluded then,
  not at completion).
* `owner-recovery cancel` (D21, S1-07; MEDIUM, no fix): the person being recovered — in the hijack case the account in the
  attacker's hands — and any org_admin of any reached organization can cancel the recovery without limit; support has no
  override, so the takeover cannot be undone this way.
* `iam.mfa.reset` (S1-02, S1-07; MEDIUM, no fix): `MfaResetCommand` asks a second person only for staff accounts and member
  managers; a developer with console or destructive keys on every service is reset on one iam_admin's word. The reset removes
  TOTP, security keys and trusted devices, but leaves live sessions, API tokens and step-up grants (S1-08).
* `access restore` (I10, S1-07; MEDIUM, no fix): a restore turns shares back on from the snapshot even when one was revoked later
  for a security reason (no revocation epoch, S1-06); a revived share keeps its original `granted_by` (an unbacked share is never
  flagged), while restored bindings name the restorer instead of the original grantor. A member who left on their own is pulled
  back for 90 days without their consent and without being told (the notice goes to the organization).
## What it does, and what it never does

* **Read-only.** It runs SELECTs inside a database transaction that is always rolled back, and it first puts the
  database itself into read-only mode for that transaction (`SET TRANSACTION READ ONLY` on PostgreSQL,
  `PRAGMA query_only` on SQLite), so a write would be refused by the database, not only avoided by the code. On
  production it additionally runs under a read-only database role (see "Isolation for every run"). It dispatches no
  CommandBus command, resolves no provider adapter, **calls no panel** (no aaPanel, ISPConfig, Pterodactyl, Proxmox,
  Discord, bank or registrar), writes no file and publishes no event. It has no schedule entry and no route. The test
  `tests/Feature/Platform/ForensicLookbackTest.php` ("changes nothing") checks this: only selects reach the database,
  every table count is unchanged, and no HTTP request is sent.
* **Output goes to stdout only.** You get a table by default, or JSON with `--json`. The report carries ids, masked
  IBANs (`CZ65…5399`) and, for PA-02, only the offending path. It never contains e-mail addresses, names, full IBANs,
  file contents or the rest of a scheduled command.
* **It decides nothing and sends nothing.** Whether a hit is a personal-data breach, and whether anybody is told, is
  the owner's decision (§10 O3, below).

## The baseline run: before the first Phase 0 deploy (mandatory)

D16 says the look-back runs **before** the Phase 0 fixes go live. Several sources read what stands in the database
now, not an event log: PA-01 (the panel user in `provider_bindings.meta.user_id` and `game_servers.ptero_user_id`),
TD-1 `owner_membership_not_owner` (the owner's current membership), and the G1 notes on links and hooks that are
still open. The Phase 0 fixes and their operator commands (the TASK-0035 orphan-links command, a manual re-homing
after the TASK-0033 dry-run, a membership repair) can change that state. A run after them finds nothing to report
for an exploit whose trace they repaired, and it reports CLEAN. Every report carries this rule in its
`standing_state` field (`BASELINE` line in the table).

1. **The baseline is the first run, and it is required.** Run the command on production **before the first Phase 0
   branch is deployed**. If the release that brings this command already contains the fixes, as it will
   (TASK-0038 is rebased last), run it on a **restore of the last backup taken before the first Phase 0 deploy**:
   * Restore the backup into an isolated database on a host with **no outbound network** (below). Point a checkout
     of the new release at it with `APP_ENV=production`, the isolation settings below, no queue worker and no
     scheduler. Run no operator repair command against it.
   * **Do not run migrations on the restore.** The command reads only tables that existed before Phase 0. If a
     source fails because a table is missing, record that source as *unknown (restore predates the table)*. Never
     record it as clean.
   * Write down which backup was used (its id, its timestamp and the timestamp of the first Phase 0 deploy). The
     backup must be older than the deploy.
2. Hash the baseline JSON with `sha256sum` and attach it to the cyber incident as the **first** evidence item, with
   `legal_hold: true` (see "Recording findings"). The register is not complete without it.
3. Every later run (on production after the deploy, or a re-run of one source) is **compared with the baseline and
   never replaces it**. A hit that is in the baseline and missing from a later run was repaired, not disproved. It
   stays in the register.

## Isolation for every run (mandatory)

The code reads only, and it switches the database into read-only mode for its own transaction. That is not enough on
its own: a release booted in production mode against production data, or against a restore that still holds the live
panel, registrar and bank credentials, must not be able to write, mail, queue or call out even through a mistake
outside the command (a worker started by habit, a `tinker` line, a listener nobody expected). Every run, baseline or
later, on production or on a restore, uses all of the following:

1. **A read-only database role.** On PostgreSQL, once, by the database administrator:
   ```
   CREATE ROLE onhost_forensics LOGIN PASSWORD '<generated, kept in the password manager>';
   GRANT CONNECT ON DATABASE <db> TO onhost_forensics;
   GRANT USAGE ON SCHEMA public TO onhost_forensics;
   GRANT SELECT ON ALL TABLES IN SCHEMA public TO onhost_forensics;
   ALTER ROLE onhost_forensics SET default_transaction_read_only = on;
   ```
   On a restore, also `ALTER DATABASE <restore> SET default_transaction_read_only = on`. Before the run, prove the
   role refuses a write: `psql -U onhost_forensics -d <db> -c 'CREATE TEMP TABLE forensic_probe (i int)'` must fail
   with "cannot execute CREATE TABLE in a read-only transaction". Record that line in the register entry.
2. **A separate checkout with its own `.env`**, never the live deploy directory and never `config:cache` there (a
   cached config ignores every override). Its `.env` is a root-only (`chmod 600`) copy of the production one with:
   `DB_USERNAME`/`DB_PASSWORD` of the read-only role (and `DB_HOST`/`DB_DATABASE` of the restore, on a restore),
   `MAIL_MAILER=array`, `QUEUE_CONNECTION=null`, and no queue worker or scheduler started from it. On a restore, use a
   fresh `APP_KEY` (`php artisan key:generate --show`) instead of the production one when the command starts with it:
   the command decrypts nothing, and without the production key the credentials in the restore stay unreadable.
3. **No outbound network on a restore host**: an egress firewall that denies everything except the database
   connection. A restore holds live panel, registrar, bank and Discord credentials; nothing on that host may reach
   them. On the production host the command's own test proves it sends no HTTP request (`Http::assertNothingSent`),
   and the read-only role, the null mailer and the null queue stop the rest.

Delete the separate checkout, its `.env` and the restore once the evidence is attached, and drop or lock the role
(`ALTER ROLE onhost_forensics NOLOGIN`) until the next look-back.

## Running it on production

1. Only the operator runs it, on the application host as the deploy user (the same shell as `php artisan doctor`),
   from the separate checkout under the read-only role described in "Isolation for every run". It needs no other
   credentials. It is just as good, and preferred when
   the database is large, to run it against a **restored copy** of the production database (a backup restored into
   an isolated instance, `APP_ENV=production`, no queue worker, no scheduler). The event traces are the same. For
   the baseline, the backup must predate the first Phase 0 deploy (above).
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
4. Re-run one source with `--source=<key>` while you follow up a hit. Re-run the whole report after the Phase 0
   branches are deployed and compare it with the baseline. It must not grow: a new hit after the fixes is a new
   incident. It may shrink where a repair changed standing state. Those baseline hits stay in the register.

## Reading the report

Each source row lists `checked` (table and row count), `hits`, `unknowns`, `notes` and `limits`. The verdict is:

* **HITS**: the stored data shows the trace of the exploit. `confidence` is:
  * `confirmed`: the row itself is the trace;
  * `possible`: the data allows an innocent reading, such as a partner's own new IBAN;
  * `attempt`: the operation was refused or failed. It still shows intent.
  * `use_or_attempt`: a Discord link's `last_used_at` lies after the removal. The field is written before any
    authorization, so it proves a use **or** a refused attempt. Either way, somebody still held the link.
* **UNKNOWNS**: no hit, but part of the question cannot be answered from the database. Every unknown says why and
  which tool answers it.
* **CLEAN**: nothing found and nothing left open. The fixed caveats in `limits` still apply. `aapanel_outside_root`
  is never CLEAN while aaPanel services exist, because a symlink is only on the node.

| Source key | Exploit | Hit kinds | Follow-up for hits and unknowns |
| --- | --- | --- | --- |
| `owner_demotion` | TD-1 | `owner_accepted_lower_role`, `owner_attached_lower_role`, `owner_membership_not_owner` | Each event is judged against the owner **of its moment**, read from the ownership transfers. A demotion stays a hit after the organization was later transferred away (`owner_now: false`). Look at the organization's audit trail around the audit event id. Was the owner the person who clicked, or was the invitation sent to their address by somebody else? |
| `member_without_invitation` | TD-2 | `member_attached_without_invitation` (confirmed, or possible when an invitation of the same role was accepted beside it under an address no account carries now) | Somebody with member management put a person who was not a member into the organization (`change_role` with any `user_id`), or brought a removed person back (`re_entry: true`), without their consent. From that moment the organization saw the person's e-mail and name: **this is the source that most directly discloses personal data**. An attach counts as explained only by the closest event within a minute **of the same role**: an accepted invitation to the person's current address, the organization's creation, an ownership transfer. An attach in an organization older than the audit, of somebody with no recorded past there, is an unknown. Ask the person (through the owner) whether they knew. |
| `project_grant_bypass` | TD-3 | `project_self_grant` (confirmed), `project_grant_above_own` (confirmed, or possible when the granter's role of that moment is not in the audit) | `add_project_member` never went through `mayGrant`. The hit names the role granted, the granter's organization and project role of that moment, and up to five permissions the granter did not hold. Staff are not bound by `mayGrant` by design; only their self-grants are hits. Compare with what the person did in the project afterwards (audit rows with that `project_id`). |
| `game_panel_identity` | PA-01 | `panel_user_of_other_org` (confirmed), `panel_user_shared_across_orgs` (possible) | Services on a panel user the platform did not record creating are unknowns. The Pterodactyl identity dry-run of TASK-0033 reads the panel's `external_id` for them (read-only). |
| `discord_after_removal` | G1 | `discord_used_after_removal` (use_or_attempt), `discord_command_after_removal`, `discord_ask_after_removal` (an assistant run of `/onhost ask`), `hook_run_after_removal` (an action hook whose creator had left) | `services` and `status` leave no row. A link whose person was removed and re-admitted is an unknown, because `last_used_at` keeps only the latest use. A hook URL is a bearer credential: hook runs after somebody left are unknowns, since the one who left may still hold a colleague's URL. Links and hooks still open after a removal are listed in `notes`; TASK-0035 revokes them. |
| `aapanel_outside_root` | PA-02 | `path_outside_root` (confirmed or attempt; file operations, Node.js project paths, an import's `subdir`, uploads), `cron_reaches_outside_root`, `command_reaches_outside_root` (terminal `command.run`; possible), `ftp_home_outside_root` (an FTP home the adapter passed to the panel unjailed; possible), `directive_reaches_outside_root` (a `root`/`alias`/`include` in saved directives; possible), `deploy_reaches_outside_root` (the deploy source's build command or hooks as stored **now**; possible) | Review round 3: the source reads **every** operation the code lets write on an aaPanel node, not a chosen few. The list lives in `AaPanelTraces` (service actions, operation kinds, audited uploads) and `ForensicLookbackTest` derives it from the adapter and its callers, so a new write path fails the test until it is read here. A restore, an archive restore, a backup, an import of a dump, an app install, a deployment's repository, a staging copy, a WordPress run or a migration stores no path of its own: the unknowns count them by kind, and their services are listed first. Symlinks and archive contents live only on the node, and so does whatever SFTP, SSH or the site's code made. Every aaPanel service is a standing unknown for the aaPanel tenancy dry-run of TASK-0034 (`operator:aapanel:tenancy`, read-only). Never open or run a file you find; copy it with its hash into the evidence store. |
| `token_cross_org` | PA-04 | `token_used_on_other_org` (grouped per token and organization) | Only writes are audited. The token's owner, and what the actions changed, come from the listed audit ids. **Blind spot (review round 3):** before TASK-0030 a bearer request carrying a stateful Origin or Referer was audited under the web session Sanctum started for it, not `token:<id>`, so those rows name no token. The source lists, as an unknown, every row of a person who held a live token bound to another organization, written in an organization none of their live tokens was bound to. The person's own browser writes look the same: compare the row's ip, user agent and request id with the token's `last_used_ip` and the web server log before calling it a token write. |
| `staff_own_org` | SS-1, SS-5 (EXPL-1..3), IF-8 | `staff_hold_lift_own_org`, `staff_force_purge_own_org`, `purge_without_archive` (review round 3: **any** purge or termination that skipped the final archive, in any organization and by any actor — a staff purge of a stranger's service cannot be undone and is the worse case; `own_org` says whether the actor was a member), `staff_self_grant` (a staff user attached themselves at a role, past `mayGrant`; an invitation, creation or transfer explains only an attach of its own role, so accepting a viewer invitation and raising oneself seconds later is a hit), `staff_reinstate_own_org` (possible) | Compare with the approval record and the reason in the audit row. A staff member acting on their own company without a second person is a hit even when the action was right. A staff action in an organization older than the audit is an unknown: whether they were a member then cannot be told. |
| `partner_payouts` | P1, P2 | `payout_above_allocated` (confirmed), `payout_iban_changed` (possible, a **rejected** request included) | A rejected request still wrote its IBAN into the partner, and the automatic payouts pay there. A payout with no earlier paid payout to compare with is an unknown. Finance checks the bank statement. Only the partner's written confirmation proves that a new IBAN is theirs. Do not contact the partner before the owner has decided (the partner may be the actor). |

## Recording findings

Every run is recorded, including a clean one: the register is the evidence that the look-back was done.

1. Open a **cyber incident** for the look-back, whether or not there are hits:
   `POST /v1/staff/security/incidents` with a title (`Permission holes Phase 0: forensic look-back`), severity and,
   only if a hit touches personal data, `personal_data_breach: true`. That flag starts the GDPR 72-hour timer
   (`GDPR_72H`, `GET /v1/staff/compliance/timers`). The clock runs from the moment we became **aware** of a breach,
   meaning a confirmed hit that concerns personal data. It does not run from the date of the exploit.
2. Attach the report as evidence: `POST /v1/staff/security/incidents/{case}/evidence` with `name`, `sha256` (from
   step 2 above), the private `path` and `legal_hold: true`. The **baseline** run goes first. Its name says which
   database it read, production before the deploy or the restore of backup `<id>` taken before it. Attach every
   later re-run the same way.
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
   the **owner's decision recorded**. It rests on the baseline run, and the entries name its evidence hash. A
   register without the baseline or without the owner's decision line is not finished.

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
