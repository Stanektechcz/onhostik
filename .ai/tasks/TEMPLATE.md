# {{ID}}: {{TITLE}}

- **Status:** PLANNED
- **Priority:** {{PRIORITY}}
- **Risk:** {{RISK}}
- **Owner:** {{OWNER}}
- **Reviewers:** (from .ai/DOMAIN_MAP.md; always onhost-reviewer + onhost-qa; onhost-security when .ai/SECURITY_RULES.md §1 applies)
- **Branch:** `{{BRANCH}}`
- **Worktree:** `{{WORKTREE}}`
- **Opened:** {{DATE}}

## Objective

What outcome the user gets, in one or two sentences.

## Context

Why now; links to the request, ADR, runbook, audit row, or failing test.

## Scope (owned paths — locked)

{{PATHS}}

## Out of scope

## Dependencies

Tasks or contracts that must exist first.

## Contracts affected

Endpoints, events and payload keys, schema, config keys, provider calls — or "none".

## Acceptance criteria

- [ ] …

## Required tests

Failing-first tests to write; existing tests that must stay green; critical flows touched (`.ai/baseline/baseline.json`).

## Security considerations

## Migration considerations

## Rollback

## Findings

Reviewer / QA / security findings: `SEVERITY file:line — finding — resolution`.

## Log

- {{DATE}} opened by `brain.ps1 task start`.

## Handoff

From / To · Outcome · Files · Contracts · Verification (VERIFIED / ASSUMED / NOT TESTED) · Concerns · Next.

## Integration

Gate report, review results, merge commit, post-integration gate, CI run.
