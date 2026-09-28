# Project state (AI team)

**Updated:** 2026-09-27 by the Slice-1 docs commit on `feat/TASK-0042-grants-follow-one-policy-and-access-can` (after `96d7930`) · **Integration branch:** `development` (at `3b2a9fb`, PR #24)

## What ONHOST is

ONhost Cloud Platform v4: a Laravel 13 modular monolith (`onhost-platform`) that sells and runs hosting — web, mail,
domains/DNS, VPS (Proxmox), game servers (Pterodactyl) — with ordering, invoicing, payments, wallet ledger, support,
incidents/SLA and compliance, on top of third-party panels and registrars (ISPConfig, aaPanel, WEDOS, Subreg, …).
The customer panel, public site and admin are a preserved HTML prototype made live through data seams.

## Where the truth lives

| Question | Read |
| --- | --- |
| Engineering invariants | `AGENTS.md`, `CLAUDE.md` |
| Product state, priorities, open operational work | `docs/context/CURRENT_STATE.md`, `docs/runbooks/go-live-checklist.md` |
| Findings with evidence | `docs/runbooks/production-readiness-audit.md` §7 (rows 1–136) |
| Permission program (Phase 0 and Slice 1 status, slices, exploits, rulings) | `docs/security/permission-program-2026-09-27.md` (status blocks), `docs/runbooks/breach-register.md` (holes still open), `docs/security/grant-policy.md` (Slice 1 integration) |
| Architecture and owner decisions | `.ai/DECISIONS.md` → `docs/adr/` (ADR-0007 = owner decisions of 2026-09-25; ADR-0008 = the three HIGH audit fixes; ADR-0009 = the permission program; next number 0010), `.ai/decisions/` |
| Map for agents | `.ai/ARCHITECTURE.md`, `.ai/DOMAIN_MAP.md`, `.ai/DEPENDENCY_MAP.md`, `.ai/TECH_STACK.md` |
| How we work | `.ai/DEVELOPMENT_RULES.md`, `.ai/INTEGRATION_RULES.md`, `.ai/SECURITY_RULES.md`, `.ai/TESTING.md` |
| Who is doing what now | `.\brain.ps1 task board` (live) |
| Test baseline | `.ai/baseline/baseline.md` / `.json` |
| Human knowledge base | Obsidian vault `C:\Users\medion\Desktop\ONHOST-BRAIN\ONHOST-BRAIN` (own Git repository, local commits only) |

## Integrated on `development`

| Task | Outcome | PR | Last commit |
| --- | --- | --- | --- |
| TASK-0001 | AI orchestration layer (`.ai/`, `onhost-*` agents, `/ai-*` skills, `brain.ps1 gate/task`) | #6 | `2d39dff` |
| TASK-0005 | a customer's ISPConfig id is not proof of ownership (audit row 92) | #7 | `49f3406` |
| TASK-0006 | undoing a cancellation brings back the carried sites (row 93) | #8 | `f03a4c1` |
| TASK-0007 | `svc_manage` is not a shell (row 94) | #9 | `797e1a7` |
| TASK-0008 | ISPConfig client limits are the organization's, on provisioning and plan change (row 95) | #10 | `68111fa` |
| TASK-0009 | a resume that could not switch everything on says so (row 96; docblock follow-up #12 `48c606f`) | #11 | `634ba12` |
| TASK-0010 | a partly refused suspension is not finished (row 97) | #13 | `170c0c6` |
| TASK-0011 | a deleted ISPConfig site leaves nothing of the customer behind (row 98) | #14 | `3fa6b38` |
| TASK-0012 | the primary binding is decided by the service's family (row 99) | #15 | `6c736ad` |
| TASK-0013 | an ended hosting stops pointing its DNS at the node (row 100) | #16 | `009c4b1` |
| TASK-0014 | a resource the platform did not create is not touched (row 101) | #17 | `fbf360c` |
| TASK-0015 | erasing an account is the owner's act, step-up, 14 days (row 102) | #18 | `29e4431` |
| TASK-0016 | mailbox tools act only on the service's own mailboxes (row 103) | #19 | `2426c17` |
| TASK-0003, TASK-0017 … TASK-0027, TASK-0029 … TASK-0031 | the stack: owner decisions of 2026-09-25 and the three HIGH onboarding-audit fixes (rows 104–122, ADR-0007, ADR-0008); merged 2026-09-26 | #24 | `3b2a9fb` |

## Pending: Phase 0 of the permission program (PR #25), Slice 1 on top of it, and TASK-0032 (PR #26)

The pull requests and branches of this phase (nothing unmerged is pushed or merged without the human's go-ahead):

| Pull request / branch | Content | State |
| --- | --- | --- |
| **PR #24** | the stack TASK-0017 … TASK-0031 | **merged** 2026-09-26 — `development` = `3b2a9fb` |
| **PR #25** — `feat/permission-p0` | Phase 0 of the permission program: wave 1 (TASK-0036, TASK-0037, TASK-0035, TASK-0033, TASK-0034, TASK-0038 → `1fb641e`) + two follow-ups (TASK-0036 PostgreSQL race answer, TASK-0037 e2e step-up) = `45017b7`, base `3b2a9fb` | open; `origin/feat/permission-p0` is at `45017b7` |
| `fix/TASK-0039-staff-act-as-staff-and-a-token-only-for` (the rest of PR #25) | on top of `45017b7`: wave 2 (TASK-0040 partner payouts, TASK-0041 wave-1 leftovers + the P0-16 record + the red-team fixes, tip `b77ab8a`) → TASK-0039 (P0-08, P0-09, P0-14; pins retired `daca3b5`, OpenAPI `b083de5`, P0-16 re-check fix `c4c43e2`, gate report `650f675`) → the Phase-0 docs commit | local, **not pushed**; gate PASS. `fix/TASK-0041-wave-one-leftovers-of-the-permission-pro` is at `b77ab8a`, the wave-2 part of this chain |
| `feat/TASK-0042-grants-follow-one-policy-and-access-can` (Slice 1, on top of the PR #25 chain) | on top of the Phase-0 docs commit `173b40b`: TASK-0042 (S1-01 `80322ae`, S1-02 `7752f41`, review round 1 `df1f6d3`, gate `903ad07`, S1-07 red-team fixes `2856e3c`, gate report `96d7930`) → the Slice-1 docs commit | local, **not pushed**, no pull request yet; gate PASS; Slice 1 **not signed off** (known issue 11) |
| **PR #26** — `fix/TASK-0032-a-deploy-stops-when-the-readiness-check` | TASK-0032: deploy gate and first release (onboarding audit C12, C14), base `3b2a9fb` | its own pull request, not merged (branch `0c73d22`, pushed) |

The program, its status per P0 key and what is still open: `docs/security/permission-program-2026-09-27.md`
(status header), decisions in ADR-0009, audit rows 123–133, operator steps `docs/runbooks/go-live-checklist.md` §7.
**Phase 0 is not signed off.** Every HIGH finding has its fix in code: the six HIGH of the first P0-16 round (`c59e08a`) in
TASK-0039, the HIGH of the re-check (`02b5bc5`) in `c4c43e2`. Still in the way: IF-4 staff reach (with staff
`archive.restore`) and PA-04 for tokens bound to no organization are logged but allowed until the operator turns on
`ONHOST_STAFF_REACH_ENFORCED` and `ONHOST_TOKEN_ORGANIZATION_REQUIRED`; `GET /v1/me` across organizations (MEDIUM) has no fix;
`c4c43e2` has had no independent review; the forensic baseline and the owner's Art. 33 decision are open.

Slice 1 (S1-01, S1-02 in TASK-0042): the program's Slice-1 status block, ADR-0009 "Slice 1 outcome", audit rows 134–136,
integration notes `docs/security/grant-policy.md`, operator steps `docs/runbooks/go-live-checklist.md` §8. **Slice 1 is not signed
off:** TD-6 is only recorded until `ONHOST_GRANT_CASCADE_ENABLED`; S1-07 is open (the four HIGH are fixed in `2856e3c`, the
tokens-and-automation lens runs again after S1-05/S1-06); eight MEDIUM have no fix (breach register "Still open after Slice 1");
`2856e3c` has had no independent review; S1-03 … S1-06, S1-08, S1-09 have not started.

## Baseline (`2856e3c`, Slice 1 on the Phase-0 chain, 2026-09-27)

`.\brain.ps1 gate -Task TASK-0042` (full) **PASS** on `2856e3c` (report `.ai/reports/TASK-0042-gate.md`, committed as `96d7930`):
Pint, Larastan 0 errors (`phpstan-baseline.neon` unchanged), Pest, frontend build; no regressions, no pre-existing failures.
The local serial suite on `2856e3c`: 1 935/1 935 (23 460 assertions). The gate after review round 1 (`903ad07`): Pest 1 930/1 930
(23 430 assertions). 65 migrations (`000900` grants, `000910` `access_snapshots.taken_by_role`); OpenAPI regenerated (453 paths,
513 operations). `pest-postgres` and E2E have not run on TASK-0042.

The Phase-0 chain underneath (`c4c43e2`):

`.\brain.ps1 gate -Task TASK-0039` (full) **PASS** on `02b5bc5` with exactly the 17 paths of `c4c43e2` uncommitted
(`.ai/reports/TASK-0039-gate.md`, committed as `650f675`): Pest 1 825/1 825 (22 951 assertions, serial) · Larastan 0 errors
(639 baseline entries, `phpstan-baseline.neon` unchanged since `3b2a9fb`) · Pint, frontend build PASS · no regressions, no
pre-existing failures · 63 migrations (`000890` partner payout accounts). Before the re-check fix, `02b5bc5`: 1 821/1 821. The
wave-2 chain with the pins (`22ba016`): 1 788/1 788. PostgreSQL (`pest-postgres`) and E2E only in CI — not yet run on wave 2 or
TASK-0039 (the payout and placement row locks and the shadow insert after the transaction are proven only there). The
parallel runner (`artisan test --parallel`) fails `ArchiveRestoreKeyTest` on a helper defined in another file
(`arsWebService()`, pre-existing); the serial suite is the evidence.

## Known issues

1. **`scripts/ai/tests/Config.Tests.ps1` fails** — it checks Brain config that exists only on the unmerged
   `origin/chore/onhost-brain` (open PR #1). Human decision: merge that config or adapt the test.
2. `scripts/ai/test.ps1` labels its report with `origin/development` instead of the tested revision.
3. Dependency-direction exceptions: `providers/` → Provisioning, Payments; `platform/` → Compliance (`.ai/DEPENDENCY_MAP.md`).
4. Larastan baseline: 641 entries of typing debt (never add; remove when touched).
5. **Owner decisions still open** (from the handoffs): the backup tick visiting every web/managed/mail service lands with
   the merge without a switch (TASK-0019); the per-operation exemptions of the second person (audit row 6); crediting a
   prepaid limit raise that ends early and the `ONHOST_ADDON_RENEWALS` consequences (IPv4/CDN end does not reach the
   panel) (TASK-0022); the plan-total date and terms wording (TASK-0023); the retention meaning of `backups.as_sold`
   and the mail server's backup disk (TASK-0024); the document version of the edited legal texts and the withdrawal
   legal review (TASK-0025).
6. **Unverified live** (ASSUMED in the handoffs): ISPConfig `databasequota_get_by_user` (TASK-0023), mailbox
   `backup_interval`/`backup_copies` and `mail_user_get` `sys_groupid` (TASK-0024), Proxmox storage `notes` and
   `protected` on a volume (TASK-0019/0024); PostgreSQL behaviour of the JSON-path, roll-up and prune queries
   (TASK-0023/0024) until CI `pest-postgres` runs on the stack.
7. **Follow-ups not started**: a sweep of existing ISPConfig clients' limits (TASK-0008); a query for carried sites
   purged after an undone cancellation (TASK-0006); off-site copies of server backups and the VDS/configurator `backup`
   strings nobody schedules (TASK-0019); Pterodactyl/IspConfigTools still turning a missing number into 0 (TASK-0023,
   metering phase 3); node-capacity check for resize, then cloud/data limit raises (TASK-0022); `DomainService`
   should use `verifiedApprovalIds` instead of caller-offered `approvalIds` and add-on cart lines are not checked
   against the parent's `addon_products` (TASK-0022, pre-existing); add-ons and delegated logins are not restored by
   pay-and-restore, and the manual original-method refund procedure is unwritten (TASK-0025); prototype copy still naming
   PITR and the student pages (TASK-0022, needs SurfaceRenderer seams); rewording the provider error texts that call
   adoption "an explicit operator decision", a staff path for `import.run`, mailbox import from a historical mail domain
   (TASK-0026); the customer notice `service.deletion.scheduled` promises a restore that needs `services.reinstate`
   (TASK-0025).
8. Vault: `System/CODE-MAP.md` lists ADR-0007 only after `Update-CodeMap.ps1` runs on a main checkout that has the stack.
9. **Onboarding audit (Fikoun, 2026-09-25)** — `.ai/audits/2026-09-25-onboarding-audit/`: the audit's `README.md` and
   `development-state.md`, and `response-verified-2026-09-25.md` checking all its claims against the stack tip (72 rows:
   45 confirmed, 21 partly, 5 wrong, 1 resolved by the stack; HIGH findings re-checked by adversarial challengers).
   **The three HIGH findings are fixed** and merged with PR #24 (ADR-0008, audit rows 120–122): TASK-0029 (service actions
   ask their own permission and risk; no default arm), TASK-0030 (explicit token scope map, `services:console`, no
   step-up through a token, step-up for staff writes outside the bus), TASK-0031 (VIES through `providers/Vies`, reverse
   charge on a fresh check or a staff override, partner self-billing VAT). Go-live items: the accountant's sign-off (D31.9)
   before `onhost:vat:verify --apply`, and `ONHOST_VIES_ENABLED=true` together with the rule `tax.vies_recheck`
   (`docs/runbooks/go-live-checklist.md` §6). Still open: the MEDIUM follow-ups of the handoffs — an operator command
   (`--dry-run` default) for console access made before TASK-0029, the step-up decision for game sub-users / console
   schedules, the Pterodactyl backup-rotation check on staging, family permissions (D29.8), four eyes for staff deletion
   of customer copies, `ServiceController::fileDownload` for power tokens, the route layer asking `permissionFor` without
   params (`TokenRouteScope`, `ServiceController::action`), a per-IP limit on guest VIES checks, VAT paid only to the
   published account (§109 ZDPH), the `vat_validations` erasure policy, the qualifier in the prototype's
   `onhost-content.js`, the flaky wall-clock bucket in `DiscordIntegrationTest` (TASK-0030 follow-up). The audit's other
   pages (`security-posture.md`, `production-readiness.md`, `ai-docs-and-tooling.md`, `needs-verification.md`) and its
   TASK-0028 branch are not pushed yet — **TASK-0028 is taken by that branch; TASK-0032 is the deploy gate (own branch); TASK-0033 … TASK-0041 are
   now the permission program**, so the audit response's unstarted follow-ups take the next free id when they start
   (its §6; the fail-closed op commands item was absorbed by Phase 0, TASK-0037).
10. **Permission program — what is still open after Phase 0** (P0-16 red team: first round on `c59e08a`, re-check on the
    final chain `02b5bc5`; the six HIGH and six MEDIUM of the first round and the HIGH + one MEDIUM of the re-check are fixed
    on the chain — audit rows 129, 132, 133).
    - **Logged, allowed until the operator's switch** (breach register open list): IF-4 staff reach on customer keys and staff
      `archive.restore` through a global binding (`ONHOST_STAFF_REACH_ENFORCED`, after seven empty days of
      `operator:authz:staff-reach` and P0-15); PA-04 for tokens bound to no organization (`ONHOST_TOKEN_ORGANIZATION_REQUIRED`,
      after `operator:tokens:unbound --dry-run` and a notice).
    - **Neither fixed nor pinned — each needs its own task (next free id at start):**
      - MEDIUM: `GET /v1/me` is open to tokens and returns every current membership of the person with the full organization
        record (billing e-mail, company and VAT ids, address, settings, role) — a token of organization A reads organization B
        (`AuthController::me`; PA-04 path, in the breach register; `TokenPrincipalTest` does not cover it).
      - MEDIUM: SS-7 — `support.customer_impersonate` (HIGH) is still held by `support_manager`.
      - LOW: the HTTP replay store (`IdempotencyKey`) is keyed `user:<id>` without the token or organization, so a portal answer
        can be replayed to the same person's token when key and body match.
      - LOW: `ServiceArchiveService::assertMayRestore` asks the source `backup.read` on the person, not the token view (same
        organization only; a staff person's token gets it through shadow-logged reach).
      - LOW: runs queued with a token before the release carry no `desired.token_id` and finish on the person's view; a staff
        `suspend` queued by the old code settles as the customer's pause — deploy with none in flight.
      - LOW: the command-line time lock of `onhost:staff:create` skips roles that hold CRITICAL keys but decide no approvals
        (`cloud_vps_admin`, `backup_dr_admin`) and a new approver made while the sole approver is suspended; no approver is told.
      - LOW: a Discord link code readable in a stored bus result (it is in clear in `discord_links.code` anyway); `approvePayout`
        compares only `requested_by` for legacy payouts; an `APP_KEY` rotation turns a retry into 409; the payout race test is
        simulated on SQLite (the real lock only on `pest-postgres`).
    - **Not reviewed:** the re-check fix `c4c43e2` (staff mode asks staff keys) has had no independent read-only review; its
      contract change for staff tools (auditor, IAM admin and sales lose `/v1/staff/services/{id}/actions`) and the mapping of
      keys with no staff counterpart (`service.console`, `backup.restore`, `backup.delete`, `game.manage`, `compute.vm.delete`,
      `service.panel_account.manage` stay customer keys in staff mode) are its choices.
    - **For P0-15 and later slices (TASK-0039 handoff):** P0-15 gives staff keys to what the shadow log lists before the switch
      goes on; S2-01 replaces `StaffActor::CONSOLE_FAMILIES`; S2-02 writes `ticket.meta.support_access`; a chained bus command
      from a step of a token-started run has no dedicated test.
    - Still open by design until the owner's O1 `--apply`: PA-02 (open aaPanel nodes) and PA-03 (terminal, Node.js and
      existing cron under the shared `www`). TD-7/TD-9 are closed and TD-8's re-share limit is in `GrantPolicy` (TASK-0042,
      known issue 11); mapped to slices, not started: PA-07 (S5-01), P6/P7 (S3).
    - Handoff follow-ups: `QuoteService` → `CartCapacity` asks without an organization (a paid order can wait in
      `ScheduleNodeStep`); the manual-archive "free now" message; a CommandBus action to set a game server's recorded owner
      after a re-home; the Doctor line `four eyes in effect` still describes the old waiver; the time lock sends no customer
      notice until `disclosure_restricted` (D7); finance sees only a masked IBAN; marketplace partner routes still on
      `organization.read`; `GameMigrationWorkflow` refuses a cross-panel move for an organization without an e-mail.
    - Tests that failed only in a wall-clock window during the Phase-0 runs (not in the baseline; the gate was green):
      `WithdrawalTest` date assertions between 22:00 and 24:00 UTC.
    - The owner's copy of the program (`PROGRAM-opravneni-2026-09-27.md`, outside the repository) still calls PA-04, EXPL-1..3,
      SS-4 and IF-4 wholly open in §9; the repository copy is current.

11. **Permission program — what Slice 1 left open** (TASK-0042 = S1-01/S1-02; review round 1 on `7752f41`, re-review on
    `df1f6d3`, S1-07 red team on `903ad07` with its four HIGH fixed in `2856e3c` — audit rows 134–136).
    - **Recorded, allowed until the operator's switch** (breach register "Still open after Slice 1"): TD-6, the active grants of
      a removed or demoted grantor (`ONHOST_GRANT_CASCADE_ENABLED`, after `operator:grants:cascade --dry-run` and a notice); the
      backlog before the switch is never re-examined, a cascade revocation chains through `removeMember`, no grouped undo.
    - **MEDIUM, no fix — each needs its own task (next free id at start; failing-first test):** the approving second person of
      an owner recovery may be a party (`ApprovalService::decide` has no `assertNotParty`); a transfer-mode recovery needs no
      acceptance or step-up by the heir and does not re-check the heir's role at completion; the recovered account cancels
      without limit, no support override; `MfaResetCommand` asks a second person only for staff and member managers; an MFA
      reset leaves sessions, tokens and step-up grants (S1-08); a restore revives shares a later security revocation took,
      keeps their original `granted_by` and names the restorer on restored bindings (S1-06); a restore pulls back for 90 days
      a member who left on their own, without consent or notice to them.
    - **LOW, no fix:** a service account as grantor is taken by id with no `isActive()` check and disabling one cascades nothing
      (`GrantPolicy::grantor`, `grantorBacks`, `dependents()` counts user bindings only); I12 depth 2 is unreachable (no `svc_*`
      role holds `organization.members.manage`), so its matrix cell proves a path that cannot happen; an ownership offer may go
      to a guest; a token's `expires_at` is capped by the membership's end, not `holdsUntil()` over its scopes' bindings; a
      restore does not check the account's state (disabled, erased) and does not tell the restored person.
    - **Not reviewed / not run:** `2856e3c` (the S1-07 fixes) has had no independent read-only review; `pest-postgres` and E2E
      have not run on TASK-0042 (partial unique indexes, savepoints, JSON lookups: ASSUMED on PostgreSQL).
    - **Not built:** S1-03 … S1-06, S1-08, S1-09 (no task; TASK-0043/TASK-0044 were never created); S1-10 rests on the delegated
      default O7, not confirmed by the owner in person; S1-07 must run again (tokens-and-automation lens) once S1-05/S1-06 exist.
    - `docs/security/grant-policy.md` §7 speaks of "the Team page" for undo and the ownership offer; there is no portal UI for the
      new endpoints until S1-04 (the go-live release note says so).

## Next safe steps

1. PR #24 is merged: run the post-integration gate on `development` and `.\brain.ps1 task finish` for TASK-0003 and
   TASK-0017 … TASK-0031.
2. With the human's go-ahead: push the Phase-0 chain to `feat/permission-p0` and merge **PR #25** after a green CI including
   `pest-postgres` and E2E; merge **PR #26** (TASK-0032, deploy gate).
3. Operator, in this order: the forensic baseline (`onhost:forensics:lookback`) on production **before** the first Phase-0
   deploy, then the steps of `docs/runbooks/go-live-checklist.md` §7 (token listing and `operator:tokens:unbound --dry-run`,
   Pterodactyl identity triage, aaPanel tenancy dry run → owner decision on terminal/Node.js/cron → notice → `--apply`, orphan
   links, project-role audit, partner masking notice, payout-anomaly dry run → `--apply --digest=`, then
   `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true` after the notice; `ONHOST_STAFF_REACH_ENFORCED=true` only after P0-15 and seven
   empty days of `operator:authz:staff-reach`).
4. For the Phase-0 sign-off: a task for `GET /v1/me` (failing-first test in `TokenPrincipalTest`), a read-only review of
   `c4c43e2`; the breach register's open list empty.
5. Slice 1 (S1-01/S1-02, TASK-0042) is built on top of the PR #25 chain: with the human's go-ahead it goes as its own pull
   request after PR #25, then the operator steps of `docs/runbooks/go-live-checklist.md` §8 (`operator:grants:cascade --dry-run`
   → notice → the owner's decision on `ONHOST_GRANT_CASCADE_ENABLED`). Before its sign-off: tasks for the MEDIUMs of known
   issue 11 and a read-only review of `2856e3c`.
6. **Next: Slice 2** (support, consent, undo — S2-01 … S2-11), as the plan orders it. The rest of Slice 1 has no task yet and
   stays on the list: S1-03 (family × level matrix, `svc_operate`), S1-04 (access wizard, the UI of the Slice-1 endpoints),
   S1-05 (automation grants), S1-06 (provenance, revocation epoch — S2-05 undo depends on it), S1-08 (session kill), S1-09,
   and the S1-07 re-run after S1-05/S1-06. P0-15 after seven days of an empty staff-reach shadow log. Tasks for the other
   items of known issues 10 and 11 take the next free id; the onboarding-audit follow-ups keep their list in
   `.ai/audits/2026-09-25-onboarding-audit/response-verified-2026-09-25.md` §6.
7. Start further changes with `/ai-orchestrate` or `/ai-task` (`brain.ps1 task start`, one worktree per task).
