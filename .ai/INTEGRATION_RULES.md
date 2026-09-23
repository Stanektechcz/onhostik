# Integration rules

Nothing reaches `development` because an implementer says it is finished. The orchestrator runs this gate and
records the evidence in the task file. Release to production is a separate, human decision
(`docs/runbooks/release-and-rollback.md`, `docs/runbooks/go-live-checklist.md`).

## 1. Review chain

```
implementer: self-check (diff review below) + focused tests + brain.ps1 gate -Quick      → SELF_VERIFIED
onhost-reviewer: independent review of the diff (not of the summary)                      → REVIEW_REQUIRED done
onhost-qa: tries to break it; adds missing negative/permission/edge tests                 → QA_REQUIRED done
onhost-security: mandatory when .ai/SECURITY_RULES.md §1 applies
mandatory reviewers from .ai/DOMAIN_MAP.md (architect for platform/, database for migrations, billing for money)
orchestrator: brain.ps1 gate -Task TASK-NNNN (full)                                        → READY_FOR_INTEGRATION
```

Reviews run in parallel when they are independent. Each reviewer writes its findings into the task file's
*Findings* section (or returns them to the orchestrator, who records them).

## 2. Findings

| Severity | Meaning | Before integration |
| --- | --- | --- |
| BLOCKER | wrong money, data loss, security hole, broken critical flow | must be fixed |
| HIGH | real bug or missing protection | must be fixed, or waived by the human with the reason in the task file |
| MEDIUM | maintainability, missing test for a secondary path | fix when cheap, else record |
| LOW / NOTE | style, suggestion | optional |

## 3. Implementer's diff review (before SELF_VERIFIED)

`git diff development...HEAD --stat` and the full diff. Stop and investigate on: files outside the task's owned
paths, lockfile or config changes the task did not ask for, reformatted unrelated code, debug output, commented-out
code, TODO hacks, secrets, temporary files, new `phpstan-baseline.neon` entries, changes to `apps/surfaces/*.dc.html`.

## 4. Gate before integration

All must hold and be written in the task file:

- acceptance criteria met, each with its evidence
- `brain.ps1 gate -Task TASK-NNNN` verdict `PASS` or `PASS_WITH_PREEXISTING_FAILURES` (report in `.ai/reports/`)
- no unexplained regression; every failure classified against `.ai/baseline/baseline.json`
- reviews done, BLOCKER/HIGH resolved or waived by the human
- migrations reviewed by onhost-database (reversible where feasible; PostgreSQL behaviour considered)
- rollback written for HIGH/CRITICAL risk
- branch rebased on current `development`; conflicts resolved by understanding both sides, never by picking
  "ours"/"theirs" blindly; gate rerun after the rebase

## 5. How to integrate

The history of `development` is linear. Preferred path, because CI is the only PostgreSQL run and also runs e2e and
gitleaks:

1. `git rebase development` in the task worktree, rerun the gate.
2. With the human's approval: `git push -u origin <branch>` and open a pull request into `development`
   (every push and PR is an outward action and needs the human's go-ahead each time).
3. Merge after CI is green (`tests` incl. `pest-postgres`, `security`, `e2e`), rebase-merge or fast-forward.

Local alternative, only on the human's request: in a **clean** checkout of `development`,
`git merge --ff-only <branch>`. Never merge into a checkout that has someone else's uncommitted work.

## 6. After integration

- Rerun `brain.ps1 gate` on the integrated `development` (in a clean worktree) — branch results are not enough.
- After the push, check CI (`gh run list --branch development --workflow tests --limit 3`).
- If routes changed: `php artisan onhost:openapi`; dependencies: `php artisan onhost:oss-inventory`;
  RoleCatalog/PermissionCatalog: `php artisan db:seed --class=AuthorizationSeeder --force` locally.
- Update the task file (INTEGRATED, commit SHAs), `.ai/PROJECT_STATE.md` if state or known issues changed,
  `docs/context/CURRENT_STATE.md` for product-visible changes; then `brain.ps1 task finish -Id TASK-NNNN`.
- If the baseline changed on purpose (tests added/removed in the critical flows), update `.ai/baseline/baseline.json`.

## 7. Rollback

Say only what is actually possible: `git revert <sha>` for code; a migration's `down()` only when it is written,
tested and does not drop data; configuration via the previous value; provider-side effects (created sites, VMs,
domains, charges) are **not** rolled back by a revert and need reconciliation (`onhost:doctor`, the relevant runbook).
