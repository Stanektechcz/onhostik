# ONhost control plane — guidance for Claude Code

Follow `AGENTS.md` (working rules) and `README.md` (layout, commands). Summary of what matters most when
changing this code base:

* PHP 8.3 is at `C:/Users/medion/php83/php.exe` on the development machine (`php` is not on PATH); run tests
  with `php artisan test --compact`. Python is not available in the shell; scripted edits use PHP.
* Every write goes through the `CommandBus` (command + handler + risk level); controllers only validate,
  dispatch and present. Risk HIGH means the user needs a fresh step-up, CRITICAL adds four-eyes approval.
* Provider work lives in `providers/*` behind contracts; never call vendor APIs from domains or controllers, never
  log vendor payloads, always write a contract test with `Http::fake()`.
* Events: `GenericEvent::of(...)` through the outbox; document them in `docs/architecture/events-catalog.md`
  and route them in `NotificationRouter`. Tests call `app(OutboxPublisher::class)->relayPending()`.
* The prototype surfaces in `apps/surfaces` must stay byte-identical; integrate through `SurfaceRenderer`
  seams and `apps/surfaces/api/*` only (docs/ui/template-inventory.md §6).
* Tests: Pest, one global function namespace across files (unique helper names), seed with `BaseSeeder`
  automatically; extra seeders (`CatalogSeeder`, `TaxRuleSeeder`, `LegalEntitySeeder`, `ContentSeeder`) are
  called explicitly where needed. Keep the suite green before finishing.
* After route changes run `php artisan onhost:openapi`; after dependency changes `php artisan onhost:oss-inventory`.

## Session start (every session, including subagents)

Several AI sessions work on this repository at the same time. Before changing anything:

1. `pwd`, `git status --short --branch`, `git worktree list` — never assume you are in the main checkout, and never
   touch uncommitted work that is not yours.
2. Read `.ai/PROJECT_STATE.md` (what ONHOST is, baseline, known issues, next safe steps), then run
   `.\brain.ps1 task board` (live board of active tasks and locked paths).
3. If you were given a task, read `.ai/tasks/TASK-NNNN.md` and work only in its worktree and owned paths.

Process rules: `.ai/DEVELOPMENT_RULES.md` (tasks, locks, worktrees, handoff), `.ai/INTEGRATION_RULES.md` (review chain,
gate, merge), `.ai/SECURITY_RULES.md`, `.ai/TESTING.md`. Ownership and hot files: `.ai/DOMAIN_MAP.md`.
Agents: `.claude/agents/onhost-*.md`. Workflows: `/ai-status`, `/ai-orchestrate`, `/ai-task`, `/ai-review`,
`/ai-integrate`, `/ai-release-check`.

* Any code change is a task on its own branch and worktree: `.\brain.ps1 task start -Title … -Owner onhost-… -Paths …`.
  `development` is the integration branch, never the place for parallel work.
* Evidence before claims: `.\brain.ps1 gate` (`-Quick` for critical flows) compares every failure with
  `.ai/baseline/baseline.json`; report VERIFIED / ASSUMED / NOT TESTED separately.
* Never: destructive Git, pushes/PRs/merges without the human's go-ahead, production actions, reading `.env` or
  private storage, write flows against the local dev panel instances (they point at live panels).

## Parallel sessions

Start a task from any session with `.\brain.ps1 task start …`, then open a new Claude Code session **in the printed
worktree folder** (desktop: new session → that folder; CLI: `cd ..\onhost-worktrees\TASK-NNNN-…; claude`) and say:
"Read CLAUDE.md, then work on .ai/tasks/TASK-NNNN.md as onhost-<agent>." Sessions talk through Git, task files,
`.ai/handoffs/`, `.ai/contracts/` and gate reports — not through chat history.
