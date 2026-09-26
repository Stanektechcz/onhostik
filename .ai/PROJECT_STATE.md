# Project state (AI team)

**Updated:** 2026-09-26 by TASK-0027 (docs of TASK-0029 … TASK-0031) · **Integration branch:** `development` (at `2426c17`, PR #19)

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
| Findings with evidence | `docs/runbooks/production-readiness-audit.md` §7 (rows 1–122) |
| Architecture and owner decisions | `.ai/DECISIONS.md` → `docs/adr/` (ADR-0007 = owner decisions of 2026-09-25; ADR-0008 = the three HIGH audit fixes; next number 0009), `.ai/decisions/` |
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

## Pending: the stack, one pull request

Branch `fix/TASK-0027-stack-coherence-and-the-docs-that-descri` = `development @ 2426c17` + TASK-0019, TASK-0017,
TASK-0020, TASK-0026, TASK-0024, TASK-0021, TASK-0022, TASK-0023, TASK-0025, cherry-picked TASK-0018 and TASK-0003,
TASK-0027 (coherence fixes C1–C4 and the docs) **plus TASK-0029, TASK-0030 and TASK-0031** (the three HIGH findings of
the onboarding audit, stacked on the PR #24 tip `052ceff` in that order and fast-forwarded onto this branch at `62af410`
on 2026-09-26, then this docs commit). Everything reaches `development` in **one** pull request, only with the human's
go-ahead: **#24** carries the stack and TASK-0029 … TASK-0031 (the new commits are local until the human approves the
push; #24's CI was green incl. `pest-postgres` and e2e before them). TASK-0017, TASK-0018, TASK-0019 and TASK-0003 were
also pushed on their own as PRs #20–#23, now closed as superseded by #24. What each task does:
`docs/context/CURRENT_STATE.md` (*The stack*), audit rows 104–122, ADR-0008 for TASK-0029 … TASK-0031. New behaviour that
reaches existing services is switched off by default; the operator steps are in `docs/runbooks/go-live-checklist.md` §6
(TASK-0029/0030 tighten authorization at deploy without a switch — release note there).

## Baseline (`20f05d9`, stack tip with TASK-0029 … TASK-0031, 2026-09-26)

`.\brain.ps1 gate -Task TASK-0031` (full) **PASS** on `20f05d9` (`.ai/reports/TASK-0031-gate.md`; `62af410` after it
changes only the task file and the gate report): Pest 1 558/1 558 (20 527 assertions) · Larastan 0 errors (639 baseline
entries / 1 010 suppressed; the three tasks only removed entries, none added) · Pint, frontend build PASS · no regressions,
no pre-existing failures · 62 migrations · OpenAPI regenerated (438 paths, 494 operations; one new route). Earlier stack
tip `edb635b` (2026-09-25): Pest 1 364/1 364, composer/npm audit clean, 543 routes (493 `/v1`), `Orchestration.Tests.ps1`
PASS. E2E and PostgreSQL only in CI — not yet run on TASK-0029 … TASK-0031.

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
   **The three HIGH findings are fixed** on the stack branch (ADR-0008, audit rows 120–122): TASK-0029 (service actions
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
   TASK-0028 branch are not pushed yet — **TASK-0028 is taken by that branch; the audit response's follow-up tasks
   continue at TASK-0032** (numbering in its §6).

## Next safe steps

1. Human: approve the push of the TASK-0029 … TASK-0031 commits to #24, let CI (`pest-postgres`, e2e) run on them, then
   merge PR #24 into `development` (`gh pr merge 24 --rebase`); after the merge the post-integration gate and
   `.\brain.ps1 task finish` for TASK-0003 and TASK-0017 … TASK-0031.
2. Then TASK-0032+ from the onboarding-audit response (§6: TASK-0032 deploy gate, TASK-0033 production fail-closed
   configuration, TASK-0034 …) and the MEDIUM follow-ups of known issue 9; coordinate with the second developer's
   TASK-0028 (portable `./brain`) so the two do not collide.
3. Staging: the lifecycle verification (archive on each panel, then the purge) and the restore of `s4s.electree.cz`.
4. Then the go-live checklist on the production host with the stack's operator steps (§6), each default-off switch only
   after its read-only command; `onhost:audit:provider-calls` on a production copy.
5. Start further changes with `/ai-orchestrate` or `/ai-task` (`brain.ps1 task start`, one worktree per task).
