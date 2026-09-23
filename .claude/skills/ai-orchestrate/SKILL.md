---
name: ai-orchestrate
description: Plan and coordinate a nontrivial ONHOST change as the orchestrator - decompose into tasks, check locks and coupling, define contracts, start worktrees, dispatch onhost-* agents, run reviews and the gate. Use when a request spans more than one file path or domain, or when the user asks for parallel/multi-agent work.
argument-hint: "<what the user wants>"
---

# Orchestrate: $ARGUMENTS

You are the orchestrator (main session). Subagents cannot start subagents, so all dispatch happens from here.
Rules: `.ai/DEVELOPMENT_RULES.md`, `.ai/INTEGRATION_RULES.md`. Stop and ask the human only for business intent,
credentials, destructive or outward actions (push, PR, deploy), or materially different product choices.

## 1. Discover (read-only)

- `git status --short --branch`, `git worktree list`, `.\brain.ps1 task board`, `.ai/PROJECT_STATE.md`.
- Locate the entry points, control flow, tests, models, callers and side effects of the request (targeted search;
  use the codebase graph / Explore agent for broad sweeps). Read `.ai/DOMAIN_MAP.md` + `.ai/DEPENDENCY_MAP.md`.
- Small and tightly coupled (one file / one path)? Then implement directly in a task worktree yourself — skip to 5.

## 2. Plan

For each unit of work write: outcome, owner agent, owned paths (incl. hot files), risk (LOW/MEDIUM/HIGH/CRITICAL),
priority, dependencies, contracts, acceptance criteria, required tests, reviewers. Build the dependency DAG.
Money, provisioning, auth, migrations → HIGH at least. Cross-domain or `platform/` → ask **onhost-architect** first.

## 3. Check conflicts

Overlapping paths with the board, a shared hot file, coupled modules (Services/Provisioning/Domains/Dns), sequential
migrations → serialize, merge ownership, or move the boundary. Never two write streams on one file.

## 4. Contracts

Where two tasks meet, write the contract (endpoint, method, request/response, error slugs, permissions, pagination,
events + payload keys, types) into `.ai/contracts/<TASK>-<name>.md` before starting either task.

## 5. Start and assign

For each READY task: `.\brain.ps1 task start -Title "…" -Owner onhost-<agent> -Paths "a,b" -Type feat|fix|… -Risk … -Priority …`.
Then dispatch the owner agent with: the worktree path, the task file path, the contract path, and "work only in that
worktree". Independent tasks go out in the **same message** (parallel); dependent ones wait. For long work, tell the
user how to open a separate Claude Code session in the worktree instead (see `CLAUDE.md` → Parallel sessions).

## 6. Review and QA

When an implementer returns SELF_VERIFIED, run `/ai-review TASK-NNNN` (reviewer + QA + security/domain reviewers in
parallel). CHANGES_REQUESTED → send findings back to the owner (same agent via SendMessage when possible).

## 7. Integrate

`/ai-integrate TASK-NNNN` — full gate, rebase, human-approved push/PR, CI, post-integration gate, state update,
`task finish`. Then update `.ai/PROJECT_STATE.md` and report to the user: VERIFIED / ASSUMED / NOT TESTED.

## 8. Improve

After a significant piece of work, note in the task file what caused rework (duplicate work, missing context,
conflicts, weak tests). Change a rule in `.ai/` only on such evidence.
