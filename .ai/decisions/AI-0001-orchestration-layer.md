# AI-0001: How the ONHOST AI team coordinates

- **Status:** accepted — TASK-0001 integrated into `development` on 2026-09-24 (PR #6, `2d39dff`)
- **Date:** 2026-09-24

## Context

ONHOST is developed by one human with several concurrent AI sessions (Claude Code desktop sessions, Codex, Gemini)
on one Windows machine. On 2026-09-23 a second Claude session was committing to `development` in the main checkout
while this bootstrap ran; its uncommitted files would have been mixed with any change made in the same directory.
The repository already had an "ONHOST Brain" layer (`AGENTS.md`, `docs/context/*`, `docs/generated/*`,
`scripts/ai/*`, `brain.ps1`) and an unmerged branch `origin/chore/onhost-brain` with six Claude reviewers, seven
skills, hooks, `.mcp.json`, `.gemini` and `.serena` config (7 commits ahead, 196 behind).

## Decisions

1. **Extend the Brain, do not replace it.** New tooling is added to `brain.ps1` (`gate`, `task`) and `scripts/ai/`;
   product knowledge stays in `docs/` (Obsidian vault) and `AGENTS.md`. `.ai/` holds only coordination and the
   orchestration view, linking to `docs/` instead of copying it.
2. **Durable vs live state.** Durable records (task files, handoffs, gate reports, decisions, baseline) are tracked and
   travel on the task branch into `development`. Live coordination (locks, id reservations, the ACTIVE_WORK board)
   lives once per clone in the main checkout's `.ai/state/`, which ignores itself in Git. A tracked board would be
   edited on every branch and conflict on every integration, and would never show other branches' work in time.
3. **One worktree per implementation task**, created by `brain.ps1 task start` under `..\onhost-worktrees\`, with a real
   `composer install` (a `vendor` junction makes Composer load `App\` from the main checkout — measured: the whole
   suite failed) and a `node_modules` junction (verified: `npm run build` passes).
4. **The main session is the orchestrator.** Claude Code 2.1.175 subagents cannot start subagents, so a separate
   orchestrator agent would be unable to dispatch. Orchestration is a skill (`/ai-orchestrate`) plus rules.
5. **Agents follow coupling, not job titles.** 15 `onhost-*` agents: implementers own module groups derived from the
   measured import graph (Services/Provisioning/Domains/Dns as one unit; money modules + payment providers as one
   unit; adapters separate), reviewers are read-only (tools without Edit/Write). The "Code Review" role is
   `onhost-reviewer`; "API/Integration" is `onhost-integration`; "Documentation" is `onhost-docs`. Names carry the
   `onhost-` prefix so they do not collide with the user-level `ecc:*` agents.
6. **Quality gate compares with a baseline.** `brain.ps1 gate` runs the CI tools and classifies each failing test
   against `.ai/baseline/baseline.json` as PREEXISTING or REGRESSION. It already caught one pre-existing,
   order-dependent failure on its first run.
7. **Contracts** live in the task file or `.ai/contracts/` (created on demand); HTTP contracts are published through the
   existing `php artisan onhost:openapi` → `contracts/openapi/onhost-v1.yaml`.

## Options not taken

- Merging `origin/chore/onhost-brain`: 196 commits behind, and its hooks run `update-context.ps1` after every edit.
  Left for the human to decide; its reviewer checklists were folded into the new agents.
- A tracked `.ai/ACTIVE_WORK.md` / `.ai/locks/`: conflicts and stale views (decision 2).
- Locks inside `.git/`: may be write-protected for sandboxed sessions.
- Worktrees sharing `vendor` via junction: breaks autoloading (decision 3).

## Consequences

- Live board and locks exist per machine clone; another machine sees tasks only through branches and task files.
- A worktree costs one `composer install` (~20 s from cache) and disk space; `task finish` removes it when merged.
- `scripts/ai/tests/Config.Tests.ps1` still fails until the Brain branch's config is merged or the test is adapted.

## Migration impact

None on the application: no PHP, schema, route or config change. Rollback = revert the TASK-0001 commit(s) and delete
`..\onhost-worktrees\` and the main checkout's `.ai/state/` (both untracked).
