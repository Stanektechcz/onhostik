---
name: ai-integrate
description: Integrate a reviewed ONHOST task into development - verify the integration gate, rebase, full quality gate, human-approved push and pull request, CI including PostgreSQL, post-integration gate, state update and worktree cleanup. Use only when the user asks to integrate or merge a task.
argument-hint: "TASK-NNNN"
disable-model-invocation: true
---

# Integrate $ARGUMENTS

Follow `.ai/INTEGRATION_RULES.md`. Every push, pull request or merge is an outward action: ask the human before each.

1. **Preconditions** from the task file: acceptance criteria checked with evidence; review chain done; no open
   BLOCKER/HIGH (or waived by the human with a reason); migrations reviewed by onhost-database; rollback written for
   HIGH/CRITICAL. Missing → stop and say what is missing.
2. **Rebase** in the task worktree: `git fetch origin` (read-only), `git rebase development`. Conflicts: understand both
   sides and the task intent, resolve semantically, never blanket ours/theirs. Migration number clash → renumber the
   task's migration to the next free number.
3. **Full gate:** `.\brain.ps1 gate -Task TASK-NNNN`. Verdict must be `PASS` or `PASS_WITH_PREEXISTING_FAILURES`;
   commit the report `.ai/reports/TASK-NNNN-gate.md` and the task file (status `READY_FOR_INTEGRATION`).
4. **Diff review** once more: `git diff development...HEAD --stat` — only the task's paths.
5. **Ask the human**, then: `git push -u origin <branch>` and `gh pr create --base development` with the task summary,
   gate verdict, reviews and rollback (end the PR body with the attribution line required by the session).
6. **CI:** watch `tests` (incl. `pest-postgres`), `security`, `e2e` for the PR. Red → back to the owner with the log.
7. **Merge** only with the human's go-ahead (rebase-merge / fast-forward keeps `development` linear).
8. **After merge:** gate on `development` in a clean worktree; `gh run list --branch development --workflow tests --limit 3`;
   `php artisan onhost:openapi` if routes changed (commit as a follow-up); task status `INTEGRATED` with SHAs;
   update `.ai/PROJECT_STATE.md` (and `docs/context/CURRENT_STATE.md` for product-visible changes);
   `.\brain.ps1 task finish -Id TASK-NNNN`.
9. Report: VERIFIED (commands + results), ASSUMED, NOT TESTED; the rollback command.
