---
name: release
description: Use when preparing or reviewing an ONHOST release, release candidate, deployment plan, rollback plan, or go-live decision.
---

# Release readiness

1. Read `AGENTS.md`, `docs/context/CURRENT_STATE.md`, `docs/runbooks/release-and-rollback.md`, and `docs/runbooks/go-live-checklist.md`.
2. Define the exact Git range and user-visible behavior included.
3. Run `brain.ps1 update`, `brain.ps1 release`, and review `docs/generated`.
4. Confirm migrations/backfills, provider changes, feature flags, observability, support notes, and rollback steps.
5. Stop before deployment or any production write. Present a concrete readiness report for human approval.

Output: scope, passed gates, blockers, migration/rollback notes, monitoring plan, and the exact approved production action still required.
