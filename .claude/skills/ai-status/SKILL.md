---
name: ai-status
description: Show the ONHOST AI team state - active tasks and locks, worktrees, branch and uncommitted changes, last gate verdict, baseline and known issues. Use at session start, when the user asks what is going on, or before starting new work.
---

# ONHOST status

Run and summarize (do not paste raw output):

1. `git status --short --branch` and `git worktree list` — where this session is; flag uncommitted work that is not yours.
2. `.\brain.ps1 task board` — active tasks, owners, status, dirty paths, owned paths, worktrees without a lock.
   A lock whose worktree has 0 changes and whose branch is merged is stale: suggest `task finish` / `task release`.
3. `git log --oneline -5 development` and, for each active task branch, `git log --oneline development..<branch>`.
4. `.ai/state/last-gate.json` of this worktree if present (verdict, regressions, when).
5. `.ai/PROJECT_STATE.md` → *Known issues* and *Next safe steps*.
6. Optional when asked: `gh run list --branch development --workflow tests --limit 3` (CI incl. PostgreSQL).

Answer in this shape: **Where you are** · **Active work** (table) · **Health** (gate/CI) · **Attention needed** ·
**Suggested next step**. Say NOT CHECKED for anything you did not run.
