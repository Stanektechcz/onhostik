# Daily workflow

Run commands from `C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform` (the main checkout) or from a task worktree under
`C:\Users\medion\Desktop\ONHOST-NEW\onhost-worktrees\TASK-NNNN-…`. Code changes happen only in a task worktree.

## Start work

```powershell
.\brain.ps1 status
.\brain.ps1 update
git status --short --branch
```

Read `CLAUDE.md`, `AGENTS.md`, `.ai/PROJECT_STATE.md`, the live board (`.\brain.ps1 task board`) and only the domain
material needed. Every code change is a task on its own branch and worktree, never on `development` directly:

```powershell
.\brain.ps1 task start -Title "Short outcome" -Owner onhost-<agent> -Paths "domains/X,tests/Feature/X" -Type fix
```

It creates `<type>/TASK-NNNN-<slug>` from `development`, the worktree `..\onhost-worktrees\TASK-NNNN-<slug>` and the task
file `.ai/tasks/TASK-NNNN.md`, and locks the paths (`.ai/DEVELOPMENT_RULES.md` §4–5). Describe the expected behavior and
validation in the task file before implementation.

## Choose the client

- Use Codex/ChatGPT for cross-domain planning, research, implementation, and integration.
- Use Claude Code for a focused implementation workflow such as `/billing-change`, `/provisioning-change`, or `/frontend-page`, and its specialist reviewers before handoff.
- Use Gemini after implementation for an independent diff review. Personal Google accounts now use the official Antigravity CLI (`agy`) and the workspace `onhost-reviewer`; Gemini CLI configuration remains available for organization/API-key accounts.
- Use Obsidian to browse `docs/Home.md`, ADRs, runbooks, and linked current state.

Do not paste broad file trees or logs. Ask a specific question, use Serena for symbols, and provide a Git diff or exact file list.

## Validate a change

```powershell
.\brain.ps1 test -Quick       # during focused work
.\brain.ps1 test              # before handoff or commit
.\brain.ps1 security          # before a pull request
.\brain.ps1 update
```

Add `-E2E` when a browser journey changes. `brain.ps1 audit` runs the full local sequence. `brain.ps1 release` adds Playwright and release-readiness framing, but never deploys.

## Handoff and commit

Use this compact format:

```text
Outcome: what behavior now works
Files: exact changed areas
Validation: commands and results
Risks: known limits or “none found”
Next: one concrete action
```

Review `git diff --check` and `git status`. The repository hook blocks private runtime paths, runs Gitleaks when available, and validates AI configuration. Commit only files that belong to the change (stage paths explicitly) on the task branch, run `.\brain.ps1 gate -Task TASK-NNNN` and write the handoff (`.ai/handoffs/TASK-NNNN.md`). **Pushing, opening a pull request into `development` and merging happen only with the human's explicit go-ahead** (`CLAUDE.md`, `.ai/INTEGRATION_RULES.md`); the orchestrator integrates, the task session does not.

## Authentication

Use each tool's browser/device login flow: `gh auth login -h github.com -p https -w`, `claude auth login`, and `agy` for Google's current personal-account flow. Complete credentials only in the provider's window. Never put tokens or passwords in chat, scripts, MCP files, or project settings.

Obsidian needs no account for a local vault. Open the folder `...\onhost-platform\docs`; optional Obsidian Sync credentials remain outside Git.

## Recovery

- Missing tool: run `scripts\ai\install-tools.ps1`, open a new terminal, then `brain.ps1 doctor`.
- Stale summary: run `brain.ps1 update`; generated files are safe to recreate.
- Failed gate: read the named check, fix the smallest cause, rerun that check once, then the full relevant gate.
- Broken AI configuration: use `scripts\ai\tests\Config.Tests.ps1` and revert the specific project setting.
- Production action: stop and follow the relevant runbook with explicit human approval.
