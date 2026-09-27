# Project state (AI team)

**Updated:** 2026-09-27 by the docs commit of Phase 0 of the permission program (TASK-0033 … TASK-0041) · **Integration branch:** `development` (at `3b2a9fb`, PR #24)

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
| Findings with evidence | `docs/runbooks/production-readiness-audit.md` §7 (rows 1–131) |
| Permission program (Phase 0 status, slices, exploits, rulings) | `docs/security/permission-program-2026-09-27.md` (status header), `docs/runbooks/breach-register.md` (holes still open) |
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

## Pending: Phase 0 of the permission program, and TASK-0032

The pull requests and branches of this phase (nothing unmerged is pushed or merged without the human's go-ahead):

| Pull request / branch | Content | State |
| --- | --- | --- |
| **PR #24** | the stack TASK-0017 … TASK-0031 | **merged** 2026-09-26 (`3b2a9fb`) |
| **PR #25** — `feat/permission-p0` | Phase 0 wave 1: TASK-0036, TASK-0037, TASK-0035, TASK-0033, TASK-0034, TASK-0038 rebased into one chain (`1fb641e`, base `3b2a9fb`) | open. `origin/feat/permission-p0` is at `45017b7`: two follow-up commits past `1fb641e` (TASK-0036 PostgreSQL race answer, TASK-0037 e2e step-up) that the wave-2 chain does **not** contain — reconcile before the wave-2 PR |
| **wave 2** — `fix/TASK-0041-wave-one-leftovers-of-the-permission-pro` (the next pull request, #26) | `1fb641e` → TASK-0040 (partner payouts, P0-13) → TASK-0041 (wave-1 leftovers + the P0-16 red-team record: pins, breach-register list) → this docs commit | local, gate PASS |
| `fix/TASK-0039-staff-act-as-staff-and-a-token-only-for` | TASK-0039: P0-08, P0-09, P0-14 (mode-aware Authorizer, StaffActor, token principal view, staff panel sign-on) at `17a4780`, base `1fb641e` | SELF_VERIFIED, **not on the wave-2 chain**; the integrator rebases 0039 → 0040 → 0041 |
| `fix/TASK-0032-a-deploy-stops-when-the-readiness-check` | TASK-0032: deploy gate and first release (onboarding audit C12, C14), base `3b2a9fb` | separate branch, its own pull request |

The program, its status per P0 key and what is still open: `docs/security/permission-program-2026-09-27.md`
(status header), decisions in ADR-0009, audit rows 123–131, operator steps `docs/runbooks/go-live-checklist.md` §7.
**Phase 0 is not signed off:** the P0-16 red team on `c59e08a` returned CHANGES_REQUESTED; its six HIGH findings all come
from TASK-0039 missing and are pinned as open by `tests/Feature/Security/PhaseZeroOpenItemsTest.php`.

## Baseline (`22ba016`, wave-2 chain with the P0-16 pins, 2026-09-27)

`.\brain.ps1 gate -Task TASK-0041` (full) **PASS** on `22ba016` (`.ai/reports/TASK-0041-gate.md`; `f4b824d` after it
changes only the gate report): Pest 1 788/1 788 (22 620 assertions) · Larastan 0 errors (639 baseline entries / 1 010
suppressed, unchanged) · Pint, frontend build PASS · no regressions, no pre-existing failures · 63 migrations (`000890`
partner payout accounts). The same chain before the pins (`c59e08a`): 1 779/1 779. PostgreSQL (`pest-postgres`) and E2E
only in CI — not yet run on wave 2 (the payout and placement row locks are proven only there). Earlier: the stack tip with
TASK-0029 … TASK-0031 (`20f05d9`, 2026-09-26) 1 558/1 558.

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
10. **Permission program — P0-16 red-team items still open** (red team on `c59e08a`, 2026-09-27; four lenses, all
    CHANGES_REQUESTED). Pinned as open and owned by TASK-0039 (not on the wave-2 chain): PA-04 (a token acts for any
    organization of its person), IF-4 (staff global reach on customer permissions, no shadow log), EXPL-1/2/3, SS-1, SS-14
    (staff acting as staff on customer routes, the reinstatement credit gate, the console pre-flight), SE-3/SS-5 and staff
    `backup.delete` / `archive.restore` through a global binding (one person, HIGH), SS-4/PA-06 (staff panel sign-on).
    **Neither fixed nor pinned — each needs its own task (next free id at start):**
    - MEDIUM: `transfer_ownership` never compares the actor; staff with a global `organization.close` (platform_owner) can hand
      any organization they enter to any member (`GrantPolicy::assertMayTransferOwnership`).
    - MEDIUM: the bus replay store (`IdempotencyStore`) is keyed per organization, not per person, for every header-keyed
      command other than service actions and domain commands, and the Redactor does not mask a Discord link `code` or an
      `ahk_` hook URL in stored results — another member who knows the key gets them (G12 rest).
    - MEDIUM (pre-existing, was missing from program §9): `platform/Http/Middleware/IdempotencyKey.php` keeps the raw
      response body for 24 h, unredacted, scoped `user:<id>` and before controller authorization — the plaintext of a new
      API token included — so a narrower token of the same person gets it back.
    - MEDIUM: a solo operator can defeat the IF-10 time lock by creating a second `platform_owner` with
      `onhost:staff:create` (no first-account check, no alert) and approving their own CRITICAL actions.
    - MEDIUM: SS-7 is not closed — `support.customer_impersonate` (HIGH) is still held by `support_manager`; once
      impersonation fills `onBehalfOfUserId`, a grant would carry the owner's rights and `granted_by = owner`.
    - MEDIUM: a leftover payout with an unconfirmed IBAN can be frozen, released and approved by one finance person
      (`PartnerPayouts::unfreezePayout`); nothing records that the account was confirmed with the partner.
    - LOW: `approvePayout` compares only `requested_by`, with no fallback to the audit actor for legacy payouts; organization,
      service-access and partner-portal commands keyed without the actor (G12); an `APP_KEY` rotation turns a retry into 409;
      the payout race test is simulated on SQLite (the real lock only on `pest-postgres`).
    - Still open by design until the owner's O1 `--apply`: PA-02 (open aaPanel nodes) and PA-03 (terminal, Node.js and
      existing cron under the shared `www`). Mapped to slices, not started: TD-7/TD-9 (S1-02), TD-8 (S1-01), PA-07 (S5-01),
      P6/P7 (S3).
    - Handoff follow-ups: `QuoteService` → `CartCapacity` asks without an organization (a paid order can wait in
      `ScheduleNodeStep`); the manual-archive "free now" message; a CommandBus action to set a game server's recorded owner
      after a re-home; the Doctor line `four eyes in effect` still describes the old waiver; the time lock sends no customer
      notice until `disclosure_restricted` (D7); finance sees only a masked IBAN; marketplace partner routes still on
      `organization.read`; `GameMigrationWorkflow` refuses a cross-panel move for an organization without an e-mail.
    - Tests that failed only in a wall-clock window during the Phase-0 runs (not in the baseline; the gate was green):
      `WithdrawalTest` date assertions between 22:00 and 24:00 UTC.

## Next safe steps

1. PR #24 is merged: run the post-integration gate on `development` and `.\brain.ps1 task finish` for TASK-0003 and
   TASK-0017 … TASK-0031.
2. Integrator: rebase TASK-0039 under wave 2 (0039 → 0040 → 0041, no conflicts expected beyond appended hot-file blocks);
   the eight pins of `PhaseZeroOpenItemsTest.php` turn red on purpose — delete them, keep TASK-0039's proofs, make
   RiskFloorTest:524 the CRITICAL proof, strike the list in `breach-register.md`; gate; then a new P0-16 red-team round.
3. With the human's go-ahead: merge **#25** (after reconciling `origin/feat/permission-p0` `45017b7`/`1334ed4` with the chain
   and a green CI incl. `pest-postgres` and e2e), then **#26** (wave 2 with TASK-0039); TASK-0032 (deploy gate) as its own
   pull request.
4. Operator, in this order: the forensic baseline (`onhost:forensics:lookback`) on production **before** the first Phase-0
   deploy, then the steps of `docs/runbooks/go-live-checklist.md` §7 (token listing, Pterodactyl identity triage, aaPanel
   tenancy dry run → owner decision on terminal/Node.js/cron → notice → `--apply`, orphan links, project-role audit, partner
   masking notice, payout-anomaly dry run → `--apply --digest=`).
5. Open tasks (next free id at start) for the red-team items of known issue 10; the onboarding-audit follow-ups keep their
   list in `.ai/audits/2026-09-25-onboarding-audit/response-verified-2026-09-25.md` §6.
6. Then Slice 1 (S1-01 GrantPolicy I1–I12 and the matrix test, S1-02 cascade/acceptance/transfer/owner recovery, S1-03
   family × level matrix, S1-04 access wizard); P0-15 only after TASK-0039 and seven days of an empty staff-reach shadow log.
7. Start further changes with `/ai-orchestrate` or `/ai-task` (`brain.ps1 task start`, one worktree per task).
