# ONHOST Brain design

**Date:** 2026-09-14
**Status:** Accepted for implementation
**Scope:** `onhost-platform`

## Goal

Create one repo-native knowledge and automation layer that ChatGPT/Codex, Claude Code, Gemini CLI, developers, and Obsidian can all use without copying the whole repository into every prompt. The system must preserve ONHOST's existing Laravel architecture and documentation, keep secrets out of AI context, and provide a small set of deterministic Windows commands for daily work.

## Architecture

The repository remains the source of truth. Human-maintained context lives in `docs/context`, architectural decisions remain in `docs/adr`, and machine-generated summaries live in `docs/generated`. `AGENTS.md` is the short shared entry point; `CLAUDE.md` and `GEMINI.md` point their clients to it and only add client-specific behavior.

Context is loaded in layers:

1. `AGENTS.md` — stable rules and pointers, always small.
2. `docs/context/PROJECT.md` and `CURRENT_STATE.md` — project map and active work.
3. Relevant domain docs, ADRs, runbooks, tests, and source files — loaded on demand.
4. Generated Git, test, and security snapshots — refreshed by scripts, never hand-edited.
5. Historical evidence from Git — retrieved only when a task requires it.

`brain.ps1` is the single operator interface. It delegates to `scripts/ai/*.ps1` and exposes `status`, `update`, `doctor`, `test`, `security`, `context`, `audit`, and `release`. Commands are local and read-only by default; release only performs readiness checks and never deploys.

## AI clients

- **ChatGPT/Codex:** orchestrates cross-cutting work, planning, research, review, and repository changes through `AGENTS.md`.
- **Claude Code:** implements focused tasks and can call project agents and skills from `.claude`. Deterministic hooks block obvious secret files and refresh context after safe changes.
- **Gemini CLI:** acts as an independent reviewer. Project settings exclude secret files and use the same shared context.
- **Serena:** provides symbol-level retrieval for PHP, JavaScript, Blade, YAML, and infrastructure files when available.
- **Context7:** provides current library documentation through one remote MCP endpoint. It is optional and carries no project secrets.

MCP configuration is deliberately small: Serena for local semantic code access and Context7 for public library documentation. GitHub remains accessible through Git and `gh`, rather than another MCP server.

## Security and quality

Tracked content is scanned for secrets; local `.env`, private storage, logs, databases, test artefacts, and generated edge data are excluded from Git and AI discovery. Claude and Gemini receive explicit deny/exclusion patterns. Gitleaks is the primary local secret scanner, Composer and npm audits cover dependencies, and existing Pint, Larastan, Pest, and Playwright checks remain authoritative.

CI adds dependency review and scheduled audits without weakening existing workflows. Dependabot maintains Composer, npm, GitHub Actions, and Terraform dependencies in small grouped pull requests.

## Obsidian

`docs` is the vault so documentation stays reviewable with code. A minimal `.obsidian` configuration enables links and excludes generated/noisy paths where possible. Existing ADRs, runbooks, operations, and security notes are reused. No separate knowledge database is introduced.

## Rollback and ownership

All project changes are committed on `chore/onhost-brain`. Generated documents are reproducible and labeled. Tool installation is idempotent and kept separate from repository configuration. Removing ONHOST Brain only requires reverting this branch; it does not modify application data or production systems.

## Acceptance criteria

- `brain.ps1 status` reports Git, tools, context freshness, tests, and security state without exposing secrets.
- `brain.ps1 update` deterministically regenerates compact project snapshots.
- Claude, Gemini, and Codex resolve the same core instructions.
- Secret files are excluded from Git, Gemini environment loading, and Claude reads.
- Existing Laravel tests and build checks still pass.
- Security scans and dependency audits run locally and in CI where compatible.
- Daily operation is documented in one short workflow.
