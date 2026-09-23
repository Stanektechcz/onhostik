# Development rules for the ONHOST AI team

These rules add coordination on top of `AGENTS.md` (engineering invariants) and `README.md` (layout). When they
disagree, `AGENTS.md` wins for code, this file wins for process. Terms: **orchestrator** = the main Claude Code
session of a user request; **agent** = a subagent from `.claude/agents/onhost-*.md`; **task** = `TASK-NNNN`.

## 1. Who does what

- Only the main session orchestrates. Claude Code subagents cannot start other subagents, so an agent never
  delegates; it finishes its slice and returns a handoff. The orchestrator decomposes, assigns, reviews, integrates.
- The orchestrator implements directly when the change is small (one file or one tight path), when delegation costs
  more than the work, or when the change needs its full context. Otherwise it delegates one task per agent.
- Parallel *implementation* happens in separate sessions or agents, each in its own worktree. Never run two
  write-capable agents in one working directory. Read-only reviewers may share one.
- Default model: whatever the session runs (`model: inherit`). Escalate to the strongest model for cross-domain
  architecture, money, security or repeated failure (Brain vault note 07); use a light model for search and docs.

## 2. Session start (every session, every agent)

1. `pwd`, `git status --short --branch`, `git worktree list`: know where you are; never assume the main checkout.
2. Read `CLAUDE.md` → `.ai/PROJECT_STATE.md` → the live board (`brain.ps1 task board`) → your task file.
3. `git log --oneline -10` on your branch and on `development` for recent changes in your area.
4. Check that your task's owned paths are locked by your task and not by another (`brain.ps1 task board`).
5. Minimal sanity check before changing anything: the closest existing test for your area passes.

## 3. Task lifecycle

`DISCOVERED → PLANNED → READY → IN_PROGRESS → IMPLEMENTED → SELF_VERIFIED → REVIEW_REQUIRED → (CHANGES_REQUESTED ↺)
→ QA_REQUIRED → READY_FOR_INTEGRATION → INTEGRATING → INTEGRATED → RELEASE_READY → DONE`, plus `BLOCKED` at any point.

- The status lives in the task file line `- **Status:** <STATE>`; update it at real transitions only, with one line in
  the task's *Log* section (date, who, what changed). No per-command logging.
- Implementers may set up to `SELF_VERIFIED`. Only the orchestrator sets `READY_FOR_INTEGRATION` and later states,
  after the gates in `.ai/INTEGRATION_RULES.md`. `DONE` requires `INTEGRATED` + post-integration verification.
  Writing code never makes a task done.

## 4. Starting and ending work

```powershell
.\brain.ps1 task start -Title "Short outcome" -Owner onhost-billing -Paths "domains/Billing,tests/Feature/Billing" -Type fix -Risk HIGH -Priority P1
```

creates branch `<type>/TASK-NNNN-<slug>` from `development`, worktree `..\onhost-worktrees\TASK-NNNN-<slug>` (real
`composer install`, `node_modules` junction, empty `.env` as in CI), commits the task file from
`.ai/tasks/TEMPLATE.md`, and records a lock. Branch types: `feat fix chore docs refactor test perf ci`.
`brain.ps1 task finish -Id TASK-NNNN` removes the worktree only when it is clean and merged; branches are never
deleted automatically.

**Why not a `vendor` junction:** Composer resolves the real path of `vendor/`, so a junction makes `App\` load from
the main checkout while tests come from the worktree (seen 2026-09-23: 400+ bogus failures).

## 5. Locks and conflicts

- Locks are coordination records, not OS locks: `brain.ps1 task claim -Id … -Owner … -Paths a,b [-Worktree <path>]`,
  `task release -Id …`. They live in the main checkout's `.ai/state/coordination/` (shared by all worktrees,
  ignored by Git). The board is regenerated from them into `.ai/state/ACTIVE_WORK.md`.
- `start` and `claim` refuse overlapping scopes (exit 2). The orchestrator then picks one: serialize the tasks,
  change boundaries, merge ownership into one task, or agree an interface contract first (section 7). Never
  "just continue".
- Claim the **hot files** of `.ai/DOMAIN_MAP.md` explicitly before editing them. Record uncommitted work of other
  people or sessions with `-Worktree <their checkout>` so no agent edits it unknowingly.

## 6. Parallel or serial

Parallelize: independent discovery, reviews, test design, docs, research, unrelated features, backend and frontend
**after** their contract exists.
Serialize: migrations (one global number sequence), edits to the same hot file, `Services`/`Provisioning`/`Domains`
changes that depend on each other, anything where one output decides the other's design, dependency upgrades,
production actions (which agents never perform anyway).
Start as many implementation streams as there are independent workstreams, rarely more than three.

## 7. Contract first

When two tasks must meet (backend ↔ frontend, domain ↔ adapter), write the contract before either implements:
endpoint, method, request/response schema, error slugs (`{error, message, status, ...extra}`), permissions,
pagination, events and payload keys (mind the outbox's key-fragment redaction), data types. Put it in the task file's
*Contracts* section, or in `.ai/contracts/<TASK>-<name>.md` when several tasks share it. HTTP contracts become code
through `routes/api.php` and are published by `php artisan onhost:openapi` into `contracts/openapi/onhost-v1.yaml`.

## 8. Handoff

Every agent ends with a handoff: in its final message and, when another session continues, in
`.ai/handoffs/TASK-NNNN.md`. Keep it short and factual:

```
From / To:        onhost-billing → orchestrator
Task / Status:    TASK-0007 / SELF_VERIFIED
Outcome:          what behaviour now works (one or two sentences)
Files:            exact paths changed
Contracts:        endpoints, events, schema, config keys changed (or "none")
Verification:     commands run and results, split into VERIFIED / ASSUMED / NOT TESTED
Concerns:         risks, open questions, findings not fixed
Next:             one concrete next step
```

## 9. Honest reporting

- Say "tests pass" only after running them; name the command. Say "no regressions" only after the relevant gate ran.
  Never say "production safe": local runs prove local behaviour; CI PostgreSQL and staging are separate evidence.
- A failure that also exists on the baseline is PREEXISTING (`.ai/baseline/baseline.json`); prove it by running the
  same test at the base revision. Never attribute it to the task, never silently fix unrelated tests.
- Review findings use BLOCKER / HIGH / MEDIUM / LOW / NOTE with file:line and evidence.

## 10. Commits

Conventional subjects as in `git log` (`feat(scope): …`, `fix(…)`, `docs: …`, `chore(ai): …`), one logical change
per commit, no mass reformatting, no lockfile changes unless the task is a dependency change. Stage paths
explicitly; never `git add -A` in a checkout someone else works in. The pre-commit hook blocks private paths.

## 11. Interruptions, priority, debt

- A new request during work is classified URGENT INTERRUPT / DEPENDENT / INDEPENDENT / BACKLOG. Before switching,
  write the current state into the task file (status, log line, next step). Nothing is dropped silently.
- Priority: P0 outage, breach, money corruption, data loss · P1 broken critical flow, payment/provisioning failure,
  serious regression · P2 important feature/UX · P3 optimization/refactor. Explicit user priority wins.
- Debt found on the way goes into the task's *Findings*; fix it now only when the task needs it or it is a severe
  security/correctness risk. Otherwise add one line to *Known issues* in `.ai/PROJECT_STATE.md`.

## 12. Never

Destructive Git (`reset --hard`, `clean -fd`, `checkout -- .`, `restore .`, `push --force`, deleting others'
branches) without the human's explicit request; production writes, deployments, migrations or seeders against
production; reading or printing secrets (`.env`, `storage/app/private`, provider credentials); write flows against
the local dev provider instances, which point at **live** panels; editing the prototype surfaces.
