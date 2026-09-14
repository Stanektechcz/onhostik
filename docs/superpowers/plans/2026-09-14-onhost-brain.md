# ONHOST Brain Implementation Plan

> **For Claude/Codex:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a secure, repo-native shared AI development system with a single PowerShell interface and concise, selectively loaded project context.

**Architecture:** Keep repository documentation and Git history as the source of truth. Add thin client adapters for Codex, Claude, Gemini, and Obsidian; deterministic scripts generate compact status snapshots; CI and local commands enforce existing quality and security rules.

**Tech Stack:** Windows PowerShell 5.1+, PHP 8.3/Laravel 13, Composer, Node/Vite, Pest, Larastan, Pint, Playwright, GitHub Actions, Gitleaks, Serena, Context7, Obsidian.

---

### Task 1: Establish the shared context contract

**Files:**
- Modify: `AGENTS.md`, `CLAUDE.md`, `.gitignore`
- Create: `GEMINI.md`
- Create: `docs/context/{PROJECT,CURRENT_STATE,DOMAIN_MAP,GLOSSARY}.md`
- Create: `docs/design/DESIGN_SYSTEM.md`

- [ ] Keep stable instructions concise and link to existing ADRs/runbooks.
- [ ] Record current priorities, risks, domain ownership, and vocabulary.
- [ ] Document existing design tokens and UI rules from the codebase.
- [ ] Exclude local secrets, private evidence, logs, databases, and generated artefacts.

### Task 2: Build deterministic Brain commands test-first

**Files:**
- Create: `brain.ps1`
- Create: `scripts/ai/{common,update-context,doctor,test,security,status}.ps1`
- Create: `scripts/ai/tests/Brain.Tests.ps1`
- Create: `docs/generated/{PROJECT_STATE,RECENT_CHANGES,TEST_STATUS,SECURITY_STATUS}.md`

- [ ] Write an executable contract test for `status`, `update`, invalid commands, and secret redaction.
- [ ] Run it and capture the expected initial failure.
- [ ] Implement command dispatch, discovery, checks, and deterministic generators.
- [ ] Re-run the contract test and verify repeatable output.

### Task 3: Configure AI clients and selective retrieval

**Files:**
- Create: `.claude/settings.json`, `.claude/agents/*.md`, `.claude/skills/*/SKILL.md`, `.claude/hooks/*.ps1`
- Create: `.gemini/settings.json`, `.geminiignore`
- Create: `.mcp.json`, `.serena/project.yml`
- Create: `scripts/ai/install-tools.ps1`

- [ ] Configure deny lists for secrets and private runtime data.
- [ ] Add focused Claude reviewers and repeatable domain workflows.
- [ ] Configure project-scoped Serena and remote Context7 without credentials.
- [ ] Make optional tool installation idempotent and version-reporting.
- [ ] Validate all JSON/YAML/frontmatter structures.

### Task 4: Add GitHub quality and security automation

**Files:**
- Create: `.github/dependabot.yml`
- Create: `.github/workflows/quality-security.yml`
- Create: `.gitleaks.toml`
- Create: `.githooks/pre-commit`
- Modify: `composer.json`, `package.json` only if script aliases materially simplify use.

- [ ] Add Composer, npm, GitHub Actions, and Terraform update groups.
- [ ] Add Larastan, dependency audits, secret scanning, and CodeQL-compatible checks.
- [ ] Keep hooks fast; full checks run through `brain.ps1` and CI.
- [ ] Test hooks against safe content and a synthetic secret fixture outside Git.

### Task 5: Make docs an Obsidian vault and document operations

**Files:**
- Create: `docs/.obsidian/*.json`
- Create: `docs/Home.md`
- Create: `docs/development/{AI_BRAIN,DAILY_WORKFLOW}.md`

- [ ] Add a useful vault home page linking current state, ADRs, runbooks, security, and design.
- [ ] Document daily commands, client roles, handoff format, context refresh, and recovery.
- [ ] Document authentication without recording tokens or passwords.

### Task 6: Install, authenticate, validate, and integrate

**Files:**
- Update: generated status documents
- Update: `docs/context/CURRENT_STATE.md`

- [ ] Install justified missing tools and initialize Serena.
- [ ] Complete browser/device authentication for GitHub, Claude, and Gemini with the user.
- [ ] Run Brain contract tests, PHP tests, Pint, Larastan, frontend build, Playwright where feasible, and security scans.
- [ ] Compare generated output across two runs for determinism.
- [ ] Attach the existing Desktop project to Git without deleting local runtime data.
- [ ] Commit auditable changes, push the feature branch, and leave the working tree clean except documented local artefacts.
