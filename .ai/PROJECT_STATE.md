# Project state (AI team)

**Updated:** 2026-09-24 by TASK-0001 · **Integration branch:** `development`

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
| Architecture decisions | `.ai/DECISIONS.md` → `docs/adr/`, `.ai/decisions/` |
| Map for agents | `.ai/ARCHITECTURE.md`, `.ai/DOMAIN_MAP.md`, `.ai/DEPENDENCY_MAP.md`, `.ai/TECH_STACK.md` |
| How we work | `.ai/DEVELOPMENT_RULES.md`, `.ai/INTEGRATION_RULES.md`, `.ai/SECURITY_RULES.md`, `.ai/TESTING.md` |
| Who is doing what now | `.\brain.ps1 task board` (live) |
| Test baseline | `.ai/baseline/baseline.md` / `.json` |
| Human knowledge base | Obsidian vault `C:\Users\medion\Desktop\ONHOST-BRAIN\ONHOST-BRAIN` (see known issue 4) |

## Orchestration state

- **TASK-0001 — AI orchestration bootstrap** (`.ai/tasks/TASK-0001.md`): branch `chore/TASK-0001-ai-orchestration`,
  worktree `C:\Users\medion\Desktop\ONHOST-NEW\onhost-worktrees\TASK-0001-ai-orchestration`. Awaiting the human's
  decision to integrate (push/PR). Until it is merged, `.ai/`, the `onhost-*` agents, the `/ai-*` skills and
  `brain.ps1 gate|task` exist only on that branch.
- No other task is open. Live locks: TASK-0001 only.

## Baseline (`8e4614a`, 2026-09-23)

Pest 856/856 · Larastan 0 errors (1 013 suppressed by the baseline file) · Pint, Vite build, composer/npm audit clean ·
59 migrations apply on an empty SQLite file · E2E and PostgreSQL only in CI.

## Known issues

1. **Order-dependent test** — `RenewalGuardTest` fails after `CheckoutTest` (Comgate credentials left in the process
   environment). Pre-existing; listed in `known_failing_tests`; a follow-up task was suggested to the user.
2. **`scripts/ai/tests/Config.Tests.ps1` fails on `development`** — it checks Brain config that exists only on the
   unmerged `origin/chore/onhost-brain` (7 ahead, 196 behind). Human decision: merge that config or adapt the test.
3. `scripts/ai/test.ps1` labels its report with `origin/development` instead of the tested revision.
4. Stale path **text** (not links): `docs/development/DAILY_WORKFLOW.md:3` and the vault note `01-Start-here.md:11`
   still name the old clone `C:\Users\medion\Desktop\ONHOST-BRAIN\ONHOST-NEW\onhost-platform` (on `chore/onhost-brain`).
   The vault's `Project-Docs` junction itself already targets the canonical `Desktop\ONHOST-NEW\onhost-platform\docs`
   (re-pointed 2026-09-16, vault `System/AUDIT-2026-09-16.md`), and all vault scripts use the canonical path.
5. Dependency-direction exceptions: `providers/` → Provisioning (15), Payments (2); `platform/` → Compliance (1).
6. Larastan baseline: 641 entries of typing debt (never add; remove when touched).

## Next safe steps

1. Human: review TASK-0001 (`git diff development...chore/TASK-0001-ai-orchestration`) and decide integration.
2. After merge: `.\brain.ps1 task finish -Id TASK-0001`; start the next change with `/ai-orchestrate` or `/ai-task`.
3. Fix known issue 1 (small test-isolation task) and decide on issue 2 and 4.
