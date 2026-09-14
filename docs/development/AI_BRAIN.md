# ONHOST Brain

ONHOST Brain is a small repository layer shared by developers, Codex/ChatGPT, Claude Code, Gemini CLI, Serena, and Obsidian. Git and the existing documentation remain authoritative; Brain selects and refreshes context but does not copy the project into another database.

## Context budget

Load context in this order and stop when the task is clear:

1. `AGENTS.md` — stable invariants.
2. `docs/context/CURRENT_STATE.md` — current priorities and risk.
3. `docs/context/DOMAIN_MAP.md` — where the change belongs.
4. One relevant ADR, runbook, test, and source slice.
5. `docs/generated` or Git history only for a specific question.

Serena is read-only and retrieves symbols without loading whole files. Context7 supplies current public library documentation. Neither receives credentials. Qdrant and a separate RAG database are intentionally absent until repository-scale evidence justifies the operational cost.

## Client roles

- **Codex/ChatGPT:** architecture, orchestration, implementation, research, and final integration.
- **Claude Code:** focused implementation and project skills; six read-only reviewers isolate specialist checks.
- **Gemini CLI:** independent review of a concrete diff and validation evidence.
- **Obsidian:** human navigation and linked project notes in `docs`.

Every handoff uses five fields: outcome, changed files, validation, risks, next action. This keeps the receiving model from reconstructing the whole session.

## Safety model

`.gitignore`, `.geminiignore`, Gemini's `advanced.ignoreLocalEnv`, Claude deny rules, a Claude pre-tool hook, the Git pre-commit hook, Gitleaks, and CI form independent layers. Secret values must never appear in prompts or reports. Production changes, destructive Git operations, credentials, payments, DNS/provider writes, migrations, and deployment always require explicit human approval.

Project-scoped configuration is versioned. Personal login state remains in each tool's user profile and is never copied into this repository.

## Maintenance

- Run `brain.ps1 update` after a meaningful change or handoff.
- Keep `AGENTS.md` stable and short; put detail in ADRs/runbooks.
- Update `CURRENT_STATE.md` when priorities or known risks change.
- Accept Dependabot updates in small reviewed pull requests after CI passes.
- Review AI/MCP configuration and security exclusions quarterly or when a client changes its schema.
- Remove obsolete notes instead of accumulating duplicated summaries.
