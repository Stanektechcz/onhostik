# Current state

**Updated:** 2026-09-14
**Branch:** `chore/onhost-brain`

## Now

- Use the shared ONHOST Brain context, automation, AI client configuration, and security gates.
- Preserve the current application behavior and existing prototype surfaces.
- Connect the Desktop working copy to GitHub without deleting local runtime data.

## Verified baseline

- Remote source: `github.com/Stanektechcz/onhostik`, default branch `development`.
- Laravel suite: 350 tests and 9,287 assertions pass on current `development` plus Brain changes.
- Pint and the frontend production build pass. Larastan now enforces level 0 incrementally against 16 exact existing findings.
- Gitleaks history scan, Composer audit, and npm audit pass.
- GitHub CLI, Codex/ChatGPT, and Claude Code are authenticated; Serena and Context7 are configured for Codex.
- Existing stack includes Pest, Pint, Larastan, Playwright, GitHub Actions, Ansible, Molecule, and OpenTofu.

## Known risk

- The production-readiness audit lists unresolved P0/P1 items; use `docs/runbooks/production-readiness-audit.md` and `docs/runbooks/go-live-checklist.md` before release work.
- A local `.env` contains provider credentials. It is ignored and must never enter prompts, logs, commits, generated context, or scans that print findings verbatim.
- Google no longer accepts personal-account OAuth from Gemini CLI. The official Antigravity CLI fallback is authenticated, its optional interaction-data collection is disabled, and native Serena/Context7 MCP entries are enabled. A read-only Gemini 3.1 Pro review completed against a Gitleaks-clean, limited diff bundle without repository-wide access.
- The existing Playwright suite reaches the isolated application, but five scenarios currently fail in panel navigation/session assertions; no application behavior was changed on this tooling branch.
- Local runtime data under `storage` is large and private; it remains outside Git and AI retrieval.

## Next decision

After ONHOST Brain validation, triage the production-readiness findings into small reviewed changes. Do not combine those application changes with this tooling branch.
