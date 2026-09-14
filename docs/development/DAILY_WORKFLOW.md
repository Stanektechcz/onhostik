# Daily workflow

Run commands from `C:\Users\medion\Desktop\ONHOST-BRAIN\ONHOST-NEW\onhost-platform`.

## Start work

```powershell
.\brain.ps1 status
.\brain.ps1 update
git status --short --branch
```

Read `AGENTS.md`, `docs/context/CURRENT_STATE.md`, and only the domain material needed. Create a focused branch from `development`. Describe the expected behavior and validation before implementation.

## Choose the client

- Use Codex/ChatGPT for cross-domain planning, research, implementation, and integration.
- Use Claude Code for a focused implementation workflow such as `/billing-change`, `/provisioning-change`, or `/frontend-page`, and its specialist reviewers before handoff.
- Use Gemini after implementation for an independent diff review. Ask it for severity-ranked findings with file/line evidence.
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

Review `git diff --check` and `git status`. The repository hook blocks private runtime paths, runs Gitleaks when available, and validates AI configuration. Commit only files that belong to the change; open a pull request into `development`.

## Authentication

Use each tool's browser/device login flow: `gh auth login -h github.com -p https -w`, `claude auth login`, and Gemini's Google login shown on first launch. Complete credentials only in the provider's window. Never put tokens or passwords in chat, scripts, `.mcp.json`, or project settings.

Obsidian needs no account for a local vault. Open the folder `...\onhost-platform\docs`; optional Obsidian Sync credentials remain outside Git.

## Recovery

- Missing tool: run `scripts\ai\install-tools.ps1`, open a new terminal, then `brain.ps1 doctor`.
- Stale summary: run `brain.ps1 update`; generated files are safe to recreate.
- Failed gate: read the named check, fix the smallest cause, rerun that check once, then the full relevant gate.
- Broken AI configuration: use `scripts\ai\tests\Config.Tests.ps1` and revert the specific project setting.
- Production action: stop and follow the relevant runbook with explicit human approval.
