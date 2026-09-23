# Active work

The coordination board is **live state**, not a tracked file, so that parallel branches never conflict on it and every
worktree sees the same board at once:

```powershell
.\brain.ps1 task board          # prints and regenerates the board
```

It lives in the main checkout at `.ai/state/ACTIVE_WORK.md`, generated from the locks in
`.ai/state/coordination/locks/` (both ignored by Git). Columns: task, title, owner, risk, status (read from the task
file), branch, uncommitted paths, owned paths, since — plus worktrees that have no lock.

Durable history is in Git: task files `.ai/tasks/TASK-NNNN.md` (on the task branch, merged with it), handoffs
`.ai/handoffs/`, gate reports `.ai/reports/`. Why this split: `.ai/decisions/AI-0001-orchestration-layer.md`.
